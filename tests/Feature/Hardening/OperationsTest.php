<?php

namespace Tests\Feature\Hardening;

use App\Livewire\Search;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\Team;
use App\Models\User;
use App\Services\Ops\OpsCheck;
use App\Services\Seminars\BillingMarketplace;
use App\Services\Seminars\SeminarService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Failures that used to be silent are now seen (enterprise audit C-17, C-28, C-13, B-07, observability). */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function outboxRow(string $table, array $over = []): void
    {
        $base = ['event_id' => (string) Str::uuid(), 'type' => 't', 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()];
        DB::table($table)->insert(array_merge($base, $over));
    }

    // ---- ops:check -------------------------------------------------------------------------------------------------------

    public function test_parked_and_stale_outbox_rows_are_counted_and_healthy_ones_are_not(): void
    {
        $this->outboxRow('reward_outbox', ['failed_at' => now()->subDay()]);                       // parked after its last retry
        $this->outboxRow('reward_outbox', ['created_at' => now()->subHours(7)]);                   // never delivered for 7 hours
        $this->outboxRow('reward_outbox', ['created_at' => now()->subMinutes(5)]);                 // normal: waiting its turn
        $this->outboxRow('reward_outbox', ['sent_at' => now()->subDay()]);                          // delivered

        $found = app(OpsCheck::class)->run();

        $this->assertSame(1, $found['outbox_points_parked']);
        $this->assertSame(1, $found['outbox_points_backlog']);
        $this->assertSame(0, $found['outbox_memory_parked']);
    }

    public function test_unconfirmed_refunds_and_unreleased_holds_are_counted(): void
    {
        $seminar = Seminar::create(['host_id' => 1, 'title' => 'T', 'summary' => 's', 'starts_at' => now()->addDay(), 'duration_minutes' => 60, 'join_url' => 'https://x.test/a', 'status' => 'published']);
        SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => 2, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'a', 'refund_requested_at' => now()->subDays(2)]);
        SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => 3, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'b', 'refund_requested_at' => now()->subMinutes(10)]);
        SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => 4, 'status' => 'pending_payment', 'payment_status' => 'pending', 'ticket_ref' => 'c', 'hold_expires_at' => now()->subHours(3)]);

        $found = app(OpsCheck::class)->run();

        $this->assertSame(1, $found['refunds_unconfirmed_24h']);
        $this->assertSame(1, $found['holds_not_released_1h']);
    }

    public function test_a_standing_problem_is_reported_once_a_day_and_the_command_says_so(): void
    {
        $this->outboxRow('reward_outbox', ['failed_at' => now()->subDay()]);
        Log::spy();

        $this->artisan('ops:check')->expectsOutputToContain('PROBLEM outbox_points_parked')->assertExitCode(1);
        $this->artisan('ops:check')->assertExitCode(1);

        Log::shouldHaveReceived('error')->withArgs(fn ($m, $ctx = []) => $m === 'ops.check' && ($ctx['kind'] ?? null) === 'outbox_points_parked')->once();
    }

    public function test_a_clean_system_exits_zero(): void
    {
        $this->artisan('ops:check')->assertExitCode(0);
    }

    // ---- scheduler heartbeat ---------------------------------------------------------------------------------------------

    public function test_ready_turns_degraded_when_the_scheduler_has_not_run_for_a_quarter_of_an_hour(): void
    {
        $ops = app(OpsCheck::class);
        $this->get('/ready')->assertOk();   // never seen: unknown is not a failure

        $ops->beat();
        $this->get('/ready')->assertOk();

        Carbon::setTestNow(now()->addMinutes(16));
        try {
            $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'degraded']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_heartbeat_and_the_check_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(fn ($e) => $e->description === 'ops-heartbeat' && $e->expression === '* * * * *'));
        $this->assertTrue($events->contains(fn ($e) => str_contains((string) $e->command, 'ops:check') && $e->expression === '*/15 * * * *'));
    }

    // ---- request id and logs ---------------------------------------------------------------------------------------------

    public function test_every_response_carries_a_request_id_and_the_logs_share_it(): void
    {
        $r = $this->get('/login');
        $id = $r->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $id);
        $this->assertSame($id, Log::sharedContext()['request_id'] ?? null);
        $this->assertNotSame($id, $this->get('/login')->headers->get('X-Request-Id'), 'a new id per request');
    }

    public function test_a_plain_caller_id_is_kept_and_a_hostile_one_is_replaced(): void
    {
        $this->get('/login', ['X-Request-Id' => 'monitor-abc123'])->assertHeader('X-Request-Id', 'monitor-abc123');

        $bad = "x\r\nSet-Cookie: a=b";
        $got = $this->get('/login', ['X-Request-Id' => $bad])->headers->get('X-Request-Id');
        $this->assertNotSame($bad, $got);
        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', (string) $got);
    }

    public function test_logs_rotate_daily_and_mail_has_a_timeout(): void
    {
        $this->assertSame(['daily'], config('logging.channels.stack.channels'));
        $this->assertSame(14, config('logging.channels.daily.days'));
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
    }

    // ---- cancel loop -----------------------------------------------------------------------------------------------------

    public function test_cancelling_marks_every_refund_before_the_first_call_to_billing(): void
    {
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 's', 'seminars.paid.enabled' => true]);
        $host = User::factory()->create(['email_verified_at' => now()]);
        $seminar = Seminar::create(['host_id' => $host->id, 'title' => 'T', 'summary' => 's', 'starts_at' => now()->addDays(2), 'duration_minutes' => 60, 'join_url' => 'https://x.test/a', 'status' => 'published', 'price_cents' => 1000, 'currency' => 'ZAR']);
        $regs = collect([11, 12, 13])->map(fn ($uid) => SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => $uid, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => "t$uid", 'amount_cents' => 1000, 'currency' => 'ZAR', 'paid_at' => now()]));

        // Not a Billing error (those are caught and retried): the request itself is cut off at the second ticket, as a PHP time limit would.
        $calls = 0;
        $this->mock(BillingMarketplace::class, function ($m) use (&$calls) {
            $m->shouldReceive('refund')->andReturnUsing(function () use (&$calls) {
                if (++$calls === 2) {
                    throw new \RuntimeException('the request was cut off part-way');
                }
            });
        });

        try {
            app(SeminarService::class)->cancel($seminar);
            $this->fail('the cut-off should have escaped');
        } catch (\RuntimeException) {
        }

        foreach ($regs as $reg) {
            $this->assertNotNull($reg->fresh()->refund_requested_at, 'ticket '.$reg->ticket_ref.' must already be marked, so the sweep retries it');
        }
    }

    // ---- header search ---------------------------------------------------------------------------------------------------

    public function test_the_cross_platform_search_is_limited_per_person(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $key = 'search-external:'.$user->id;
        foreach (range(1, 40) as $i) {
            RateLimiter::hit($key, 60);
        }

        Livewire::actingAs($user)->test(Search::class)->set('query', 'invoice template')->call('loadExternal')->assertSet('external', []);

        $this->assertSame(40, RateLimiter::attempts($key), 'the refused call did not count');

        // And a person under the limit is counted, so the limit means something.
        $other = User::factory()->create(['email_verified_at' => now()]);
        $other->forceFill(['current_team_id' => $team->id])->save();
        Livewire::actingAs($other)->test(Search::class)->set('query', 'invoice template')->call('loadExternal');
        $this->assertSame(1, RateLimiter::attempts('search-external:'.$other->id));
    }
}
