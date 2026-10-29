<?php

namespace Tests\Feature\Licensing;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\InviteTeamMember;
use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use App\Services\Licensing\SeatLedger;
use App\Services\Licensing\SeatLimitReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** R-13 seat licensing: three free seats including the owner, held by invitations, race-safe, never removing anyone, never inventing a price. */
class SeatLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);

        return [$owner, $team];
    }

    private function invite(User $owner, Team $team, string $email): void
    {
        app(InviteTeamMember::class)->invite($owner, $team, $email, 'member');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['licensing.included_seats' => 3, 'licensing.price.amount_minor_units' => null, 'licensing.price.currency' => null, 'licensing.billing_connected' => false]);
    }

    public function test_the_owner_takes_one_of_three_free_seats_so_two_people_can_be_invited(): void
    {
        [$owner, $team] = $this->company();

        $this->invite($owner, $team, 'a@example.test');
        $this->invite($owner, $team, 'b@example.test');

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame([3, 1, 2, 0], [$s['capacity'], $s['people'], $s['reserved'], $s['available']]);

        $this->expectException(SeatLimitReached::class);
        $this->invite($owner, $team, 'c@example.test');
    }

    public function test_the_refusal_says_what_is_true_and_that_nothing_was_charged_or_removed(): void
    {
        [$owner, $team] = $this->company();
        $this->invite($owner, $team, 'a@example.test');
        $this->invite($owner, $team, 'b@example.test');

        try {
            $this->invite($owner, $team, 'c@example.test');
            $this->fail('expected the seat limit');
        } catch (SeatLimitReached $e) {
            $message = $e->errors()['email'][0];
            $this->assertStringContainsString('3 seats', $message);
            $this->assertStringContainsString('nothing has been charged', $message);
            $this->assertStringContainsString('nobody has been removed', $message);
        }
        $this->assertSame(2, $team->teamInvitations()->count(), 'the refused invitation was not created');
        $this->assertSame(1, TeamLicenceEvent::where('team_id', $team->id)->where('type', 'blocked')->count());
    }

    public function test_accepting_an_invitation_does_not_count_the_seat_twice(): void
    {
        [$owner, $team] = $this->company();
        $this->invite($owner, $team, 'a@example.test');
        $this->invite($owner, $team, 'b@example.test');
        $a = User::factory()->create(['email' => 'a@example.test']);

        app(AddTeamMember::class)->add($owner, $team, 'a@example.test', 'member');
        $team->teamInvitations()->where('email', 'a@example.test')->delete();

        $s = app(SeatLedger::class)->summary($team->fresh());
        $this->assertSame([2, 1, 3, 0], [$s['people'], $s['reserved'], $s['used'], $s['available']]);
        $this->assertTrue($team->fresh()->users->contains($a));
    }

    public function test_cancelling_an_invitation_frees_its_seat(): void
    {
        [$owner, $team] = $this->company();
        $this->invite($owner, $team, 'a@example.test');
        $this->invite($owner, $team, 'b@example.test');
        $team->teamInvitations()->first()->delete();

        $this->invite($owner, $team, 'c@example.test');
        $this->assertSame(2, $team->teamInvitations()->count());
    }

    public function test_personal_workspaces_are_not_licensed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        foreach (['a', 'b', 'c', 'd', 'e'] as $n) {
            $this->invite($user, $team, "$n@example.test");
        }
        $this->assertSame(5, $team->teamInvitations()->count());
        $this->assertFalse(app(SeatLedger::class)->summary($team)['applies']);
    }

    public function test_a_company_over_capacity_keeps_everyone_and_only_new_additions_are_blocked(): void
    {
        [$owner, $team] = $this->company();
        $members = User::factory()->count(4)->create();
        foreach ($members as $m) {
            $team->users()->attach($m, ['role' => 'member']);
        }

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame(5, $s['people']);
        $this->assertSame(2, $s['over_by']);
        $this->assertSame(4, $team->users()->count(), 'nobody was removed');

        $this->expectException(SeatLimitReached::class);
        $this->invite($owner, $team, 'new@example.test');
    }

    public function test_a_company_with_no_purchases_reports_zero_paid_seats_as_a_number(): void
    {
        [, $team] = $this->company();

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame([0, 'free'], [$s['purchased'], $s['status']]);
    }

    public function test_purchased_seats_raise_capacity_without_a_price_being_invented(): void
    {
        [$owner, $team] = $this->company();
        TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => 2, 'billing_status' => 'active']);
        foreach (['a', 'b', 'c', 'd'] as $n) {
            $this->invite($owner, $team, "$n@example.test");
        }

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame(5, $s['capacity']);
        $this->assertNull($s['price'], 'no price is shown until the owner approves one');
        $this->assertFalse($s['can_buy']);
    }

    public function test_a_price_is_only_offered_when_it_is_configured_and_billing_is_connected(): void
    {
        [, $team] = $this->company();
        config(['licensing.price.amount_minor_units' => 5000, 'licensing.price.currency' => 'ZAR']);
        $this->assertFalse(app(SeatLedger::class)->summary($team)['can_buy'], 'billing is not connected');

        config(['licensing.billing_connected' => true]);
        $this->assertTrue(app(SeatLedger::class)->summary($team)['can_buy']);
    }

    public function test_the_check_and_the_change_run_in_one_transaction_that_locks_the_company(): void
    {
        [$owner, $team] = $this->company();
        $levels = [];
        app(SeatLedger::class)->guard($team, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        }, null, $owner);

        $this->assertGreaterThanOrEqual(1, $levels[0]);
    }

    public function test_ordinary_members_cannot_see_seats_and_reputation_is_not_involved(): void
    {
        [$owner, $team] = $this->company();
        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'member']);
        $admin = User::factory()->create();
        $team->users()->attach($admin, ['role' => 'admin']);

        $this->assertTrue($owner->can('viewLicence', $team));
        $this->assertTrue($admin->can('viewLicence', $team));
        $this->assertFalse($member->can('viewLicence', $team));
        $this->assertFalse($admin->can('changeLicence', $team));
        $this->assertTrue($owner->can('changeLicence', $team));

        $this->actingAs($member)->get('/teams/'.$team->id)->assertOk()->assertDontSee('data-seats', false);
        $this->actingAs($owner)->get('/teams/'.$team->id)->assertOk()->assertSee('data-seats', false)->assertSee('3 of 3', false);
    }

    public function test_removing_a_person_is_audited_and_keeps_their_account(): void
    {
        [$owner, $team] = $this->company();
        $m = User::factory()->create();
        $team->users()->attach($m, ['role' => 'member']);

        $this->actingAs($owner);
        app(\App\Actions\Jetstream\RemoveTeamMember::class)->remove($owner, $team, $m);

        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'left']);
        $this->assertNotNull(User::find($m->id));
    }

    public function test_invitations_and_joins_leave_an_audit_trail(): void
    {
        [$owner, $team] = $this->company();
        $this->invite($owner, $team, 'a@example.test');

        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'invited', 'subject' => 'a@example.test', 'actor_id' => $owner->id]);
    }
}
