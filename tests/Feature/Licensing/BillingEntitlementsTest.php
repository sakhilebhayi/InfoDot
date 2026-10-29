<?php

namespace Tests\Feature\Licensing;

use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\User;
use App\Services\Licensing\BillingEntitlements;
use App\Services\Licensing\SeatLedger;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Purchased seats come only from Dot.Billing's signed answer; any failure leaves the licence untouched. */
class BillingEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Team
    {
        $owner = User::factory()->create();

        return Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['licensing.included_seats' => 3, 'licensing.billing_connected' => true, 'licensing.billing.url' => 'https://billing.test',
            'licensing.billing.secret' => 'shh-secret', 'licensing.billing.platform' => 'infodot']);
    }

    private function answer(array $over = []): array
    {
        return array_merge(['company_uuid' => 'x', 'status' => 'active', 'plan' => ['slug' => 'team', 'name' => 'Team'], 'seat_limit' => 8, 'storage_gb' => 10, 'features' => [], 'period_end' => null, 'trial_ends_at' => null, 'cancel_at_period_end' => false], $over);
    }

    public function test_a_signed_answer_sets_the_purchased_seats_and_is_audited(): void
    {
        $team = $this->company();
        Http::fake(['billing.test/*' => Http::response($this->answer())]);

        $this->assertTrue(app(BillingEntitlements::class)->sync($team));

        $licence = TeamLicence::where('team_id', $team->id)->first();
        $this->assertSame([5, 'active', 'team'], [$licence->purchased_seats, $licence->billing_status, $licence->billing_ref]);
        $this->assertSame(8, app(SeatLedger::class)->summary($team)['capacity']);
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'capacity_changed']);
    }

    public function test_the_request_is_signed_with_the_platform_secret_and_names_the_company(): void
    {
        $team = $this->company();
        Http::fake(['billing.test/*' => Http::response($this->answer())]);

        app(BillingEntitlements::class)->sync($team);

        Http::assertSent(function (Request $r) use ($team) {
            $ts = $r->header('X-InfoDot-Timestamp')[0] ?? null;
            $sig = $r->header('X-InfoDot-Signature')[0] ?? null;

            return $r->url() === 'https://billing.test/api/entitlements/'.$team->uuid
                && ($r->header('X-InfoDot-Platform')[0] ?? null) === 'infodot'
                && Signature::valid('shh-secret', $ts, $sig, '', 300);
        });
    }

    public function test_the_free_seats_are_a_floor_and_past_due_keeps_the_seats(): void
    {
        $team = $this->company();
        Http::fake(['billing.test/*' => Http::response($this->answer(['seat_limit' => 2, 'status' => 'past_due']))]);

        app(BillingEntitlements::class)->sync($team);

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame(3, $s['capacity'], 'never below the three free seats');
        $this->assertSame('past_due', $s['status']);
    }

    public function test_an_unlimited_plan_is_stored_as_a_large_capacity(): void
    {
        $team = $this->company();
        Http::fake(['billing.test/*' => Http::response($this->answer(['seat_limit' => null]))]);

        app(BillingEntitlements::class)->sync($team);

        $this->assertGreaterThan(1000, app(SeatLedger::class)->summary($team)['capacity']);
    }

    public function test_a_failure_leaves_the_stored_licence_untouched(): void
    {
        $team = $this->company();
        TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => 4, 'billing_status' => 'active']);

        foreach ([Http::response([], 500), Http::response(['error' => 'invalid_request'], 401), Http::response(['seat_limit' => 'lots', 'status' => 'active']), Http::response('not json', 200)] as $reply) {
            Cache::flush();
            Http::fake(['billing.test/*' => $reply]);
            app(BillingEntitlements::class)->sync($team);
            $this->assertSame(4, TeamLicence::where('team_id', $team->id)->value('purchased_seats'));
        }

        Cache::flush();
        Http::fake(['billing.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        $this->assertFalse(app(BillingEntitlements::class)->sync($team));
        $this->assertSame(4, TeamLicence::where('team_id', $team->id)->value('purchased_seats'));
    }

    public function test_nothing_is_called_unless_billing_is_connected_and_configured(): void
    {
        Http::fake();
        $team = $this->company();

        config(['licensing.billing_connected' => false]);
        $this->assertFalse(app(BillingEntitlements::class)->sync($team));
        config(['licensing.billing_connected' => true, 'licensing.billing.secret' => null]);
        $this->assertFalse(app(BillingEntitlements::class)->sync($team));

        $personal = User::factory()->withPersonalTeam()->create()->currentTeam;
        config(['licensing.billing.secret' => 'shh-secret']);
        $this->assertFalse(app(BillingEntitlements::class)->sync($personal), 'personal workspaces are never sent to billing');

        Http::assertNothingSent();
    }

    public function test_answers_are_cached_so_pages_do_not_call_billing_every_time(): void
    {
        $team = $this->company();
        Http::fake(['billing.test/*' => Http::response($this->answer())]);

        $this->assertTrue(app(BillingEntitlements::class)->sync($team));
        $this->assertFalse(app(BillingEntitlements::class)->sync($team));

        Http::assertSentCount(1);
    }

    public function test_the_invitation_check_uses_the_fresh_seat_count(): void
    {
        $team = $this->company();
        $owner = $team->owner;
        Http::fake(['billing.test/*' => Http::response($this->answer(['seat_limit' => 5]))]);
        \Illuminate\Support\Facades\Mail::fake();

        foreach (['a', 'b', 'c', 'd'] as $n) {
            app(\App\Actions\Jetstream\InviteTeamMember::class)->invite($owner, $team, "$n@example.test", 'member');
        }

        $this->assertSame(4, $team->teamInvitations()->count(), 'five paid seats allow the owner plus four invitations');
    }
}
