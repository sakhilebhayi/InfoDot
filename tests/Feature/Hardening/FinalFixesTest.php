<?php

namespace Tests\Feature\Hardening;

use App\Models\Questions;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\SeminarReport;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Licensing\BillingEntitlements;
use App\Services\Ops\OpsCheck;
use App\Services\Points\RewardOutbox;
use App\Services\Seminars\BillingMarketplace;
use App\Services\Seminars\MeetingLinkCheck;
use App\Services\Seminars\SeminarService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** The remaining engineering items of the 2026-10-30 enterprise audit. */
class FinalFixesTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function seminar(User $host): Seminar
    {
        return app(SeminarService::class)->save($host, null, ['title' => 'Final', 'summary' => 'x', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r'], true);
    }

    // ---- O-02 narrow kill switch -----------------------------------------------------------------------------------------

    public function test_pausing_ticket_sales_stops_new_checkouts_but_not_refunds_or_callbacks(): void
    {
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 's', 'seminars.paid.enabled' => true, 'seminars.paid.callback_secret' => 'cb', 'seminars.paid.checkout_enabled' => false]);
        Http::fake(['billing.test/*' => Http::response(['status' => 'ok'])]);
        $host = $this->person('Host');
        $buyer = $this->person('Buyer');
        $s = $this->seminar($host)->forceFill(['price_cents' => 1000, 'currency' => 'ZAR', 'status' => 'published']);
        $s->save();

        try {
            app(SeminarService::class)->startCheckout($s, $buyer);
            $this->fail('sales must be paused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('paused', $e->errors()['seminar'][0]);
        }

        $this->assertSame(0, Http::recorded()->count(), 'nothing reached Billing');
        $this->assertTrue(app(BillingMarketplace::class)->configured(), 'the Billing client stays on, so refunds still work');
        $this->post('/api/billing/ticket-events', [])->assertStatus(401);   // refused for the bad signature, not because the endpoint is off
    }

    // ---- A-11, A-12, B-11 ------------------------------------------------------------------------------------------------

    public function test_a_companys_private_items_are_not_indexed_for_search(): void
    {
        $asker = $this->person('Asker');
        $public = $this->question($asker, 'public');
        $private = $this->question($asker, 'private');
        $solution = $this->solution($asker, 'private');

        $this->assertTrue($public->shouldBeSearchable());
        $this->assertFalse($private->shouldBeSearchable());
        $this->assertFalse($solution->shouldBeSearchable());
        $this->assertInstanceOf(Questions::class, $private);
        $this->assertInstanceOf(Solutions::class, $solution);
    }

    // ---- E-17 reporters hear back ----------------------------------------------------------------------------------------

    public function test_a_reporter_is_told_a_moderator_looked_without_being_told_what_was_written(): void
    {
        $service = app(SeminarService::class);
        $s = $this->seminar($this->person('Host'));
        $r1 = $this->person('Reporter One');
        $r2 = $this->person('Reporter Two');
        $report1 = $service->report($s, $r1, 'spam', 'private words');
        $service->report($s, $r2, 'harmful', null);
        $mod = $this->moderator();

        $service->dismissReport($report1, $mod, 'Internal note that the reporter must not see.');
        $this->assertContains(UserNotice::SEMINAR_REPORT_DECIDED, UserNotice::where('user_id', $r1->id)->pluck('kind')->all());
        $this->assertNotContains(UserNotice::SEMINAR_REPORT_DECIDED, UserNotice::where('user_id', $r2->id)->pluck('kind')->all(), 'the other report is still open');

        $service->removeByModerator($s->fresh(), $mod, 'Looks like a scam to us.');
        $this->assertContains(UserNotice::SEMINAR_REPORT_DECIDED, UserNotice::where('user_id', $r2->id)->pluck('kind')->all());

        $page = $this->actingAs($r2)->get('/notifications')->assertOk()->getContent();
        $this->assertStringContainsString('A moderator looked at your report', $page);
        $this->assertStringContainsString('The seminar was removed', $page);
        $this->assertStringNotContainsString('scam to us', $page);
        $this->assertSame(2, SeminarReport::where('seminar_id', $s->id)->count());
    }

    // ---- E-12 overdue appeals --------------------------------------------------------------------------------------------

    public function test_appeals_nobody_has_answered_for_too_long_are_flagged(): void
    {
        DB::table('reputation_appeals')->insert(['user_id' => 1, 'snapshot_id' => 1, 'message' => 'm', 'status' => 'open', 'created_at' => now()->subDays(15), 'updated_at' => now()]);
        DB::table('reputation_appeals')->insert(['user_id' => 2, 'snapshot_id' => 1, 'message' => 'm', 'status' => 'open', 'created_at' => now()->subDays(3), 'updated_at' => now()]);
        DB::table('seminar_removal_appeals')->insert(['seminar_id' => 9, 'host_id' => 1, 'message' => 'm', 'status' => 'open', 'created_at' => now()->subDays(8), 'updated_at' => now()]);

        $found = app(OpsCheck::class)->run();

        $this->assertSame(1, $found['score_appeals_open_14d']);
        $this->assertSame(1, $found['removal_appeals_open_7d']);
    }

    // ---- C-16 points outbox ----------------------------------------------------------------------------------------------

    private function pointsEnvelope(string $id, string $ref): array
    {
        return ['event_id' => $id, 'type' => 'answer_published', 'occurred_at' => now()->toIso8601String(), 'user_uuid' => 'u', 'contribution_ref' => $ref, 'visibility' => 'public', 'facts' => []];
    }

    public function test_two_overlapping_flushes_send_an_event_once(): void
    {
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'secret-for-tests']);
        $outbox = app(RewardOutbox::class);
        $outbox->enqueue($this->pointsEnvelope('11111111-1111-1111-1111-111111111111', 'answer:1'));

        $sends = 0;
        Http::fake(function () use (&$sends, $outbox) {
            $sends++;
            if ($sends === 1) {
                $outbox->flush();   // another worker starts while the first is still waiting for the ledger
            }

            return Http::response(['ok' => true], 201);
        });

        $outbox->flush();

        $this->assertSame(1, $sends, 'the second flush must find the row already claimed');
        $this->assertNotNull(DB::table('reward_outbox')->value('sent_at'));
    }

    public function test_events_about_one_contribution_stay_in_order_using_the_indexed_reference(): void
    {
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'secret-for-tests']);
        $outbox = app(RewardOutbox::class);
        $outbox->enqueue($this->pointsEnvelope('22222222-2222-2222-2222-222222222222', 'answer:5'));
        $outbox->enqueue($this->pointsEnvelope('33333333-3333-3333-3333-333333333333', 'answer:5'));
        $outbox->enqueue($this->pointsEnvelope('44444444-4444-4444-4444-444444444444', 'answer:6'));
        $this->assertSame(['answer:5', 'answer:5', 'answer:6'], DB::table('reward_outbox')->orderBy('id')->pluck('contribution_ref')->all());

        // The first event for answer:5 is refused with a retryable answer, so the second must not overtake it; answer:6 is independent.
        $order = [];
        Http::fake(function ($request) use (&$order) {
            $id = json_decode($request->body(), true)['event_id'];
            $order[] = $id;

            return $id === '22222222-2222-2222-2222-222222222222' ? Http::response([], 503) : Http::response(['ok' => true], 201);
        });

        $outbox->flush();

        $this->assertSame(['22222222-2222-2222-2222-222222222222', '44444444-4444-4444-4444-444444444444'], $order);
    }

    // ---- C-19 Billing down -----------------------------------------------------------------------------------------------

    public function test_a_failed_read_of_billing_is_not_repeated_on_every_view(): void
    {
        config(['licensing.billing_connected' => true, 'licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 's']);
        $owner = $this->person('Owner');
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $service = app(BillingEntitlements::class);

        $this->assertTrue($service->configured());

        Http::fake(['billing.test/*' => Http::response([], 503)]);
        $service->sync($team);
        $service->sync($team);
        $service->sync($team);

        $this->assertSame(1, Http::recorded()->count(), 'three views while Billing is down made one call, not three');
    }

    // ---- C-18 link check outside the transaction -------------------------------------------------------------------------

    public function test_the_meeting_link_is_checked_before_the_database_transaction_opens(): void
    {
        config(['seminars.link_check.enabled' => true]);   // phpunit.xml switches the check off for the other tests
        $host = $this->person('Host');
        $outside = DB::transactionLevel();
        $seen = null;
        MeetingLinkCheck::$resolver = function (string $h) use (&$seen) {
            $seen = DB::transactionLevel();

            return ['93.184.216.34'];
        };
        Http::fake(['*' => Http::response('', 200)]);

        try {
            app(SeminarService::class)->save($host, null, ['title' => 'T', 'summary' => 's', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r'], true);
        } finally {
            MeetingLinkCheck::$resolver = null;
        }

        $this->assertSame($outside, $seen, 'no transaction (and no row lock) is held while the network is asked');
    }

    // ---- C-13 cancel once ------------------------------------------------------------------------------------------------

    public function test_cancelling_twice_asks_for_each_refund_once(): void
    {
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 's', 'seminars.paid.enabled' => true]);
        Http::fake(['billing.test/*' => Http::response(['status' => 'ok'])]);
        $s = $this->seminar($this->person('Host'));
        $s->forceFill(['price_cents' => 1000, 'currency' => 'ZAR'])->save();
        SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $this->person('A')->id, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'ta', 'amount_cents' => 1000, 'currency' => 'ZAR', 'paid_at' => now()]);

        app(SeminarService::class)->cancel($s);
        app(SeminarService::class)->cancel($s->fresh());

        $this->assertSame(1, Http::recorded(fn ($r) => str_contains($r->url(), '/refund'))->count());
    }

    // ---- C-25 one report each --------------------------------------------------------------------------------------------

    public function test_the_database_refuses_a_second_report_by_the_same_person_about_the_same_thing(): void
    {
        $s = $this->seminar($this->person('Host'));
        $r = $this->person('Reporter');
        $row = fn (string $name) => ['seminar_id' => $s->id, 'reporter_id' => $r->id, 'reason' => 'spam', 'about_name' => $name === '' ? null : $name, 'about_key' => mb_strtolower($name), 'created_at' => now(), 'updated_at' => now()];

        DB::table('seminar_reports')->insert($row(''));
        DB::table('seminar_reports')->insert($row('Troll'));   // a report about a person is a different subject

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('seminar_reports')->insert($row('troll'));   // the same person again, in other letters
    }

    // ---- C-27 a solution is saved whole ----------------------------------------------------------------------------------

    public function test_a_solution_whose_steps_fail_to_save_leaves_nothing_behind(): void
    {
        $user = $this->person('Author');
        $user->forceFill(['current_team_id' => $user->current_team_id])->save();
        $count = 0;
        Steps::creating(function () use (&$count) {
            if (++$count === 2) {
                throw new \RuntimeException('step two failed');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs($user)->post('/solution/add', [
                'solution_title' => 'Register a company', 'solution_description' => 'The order of the steps matters.', 'tags-input' => 'cipc', 'duration' => 2, 'duration_type' => 'days',
                'solution_heading' => ['One', 'Two', 'Three'], 'solution_body' => ['a', 'b', 'c'], 'topics' => [Topic::query()->value('id')],
            ]);
            $this->fail('the failure should have escaped');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, Solutions::count(), 'no half-saved solution');
        $this->assertSame(0, Steps::count());
    }
}
