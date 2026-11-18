<?php

namespace Tests\Feature\Licensing;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use App\Services\Licensing\SeatLedger;
use App\Services\Licensing\SeatLimitReached;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** R-13/R-53 licensing: one free licence (the owner's), paid licences for everyone else, held by invitations, race-safe, never removing anyone, never inventing a price. */
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
        config(['licensing.included_seats' => 1, 'licensing.price.amount_minor_units' => null, 'licensing.price.annual_amount_minor_units' => null, 'licensing.price.currency' => null, 'licensing.finance_connected' => false]);
    }

    /** Gives a company paid licences the way a verified purchase does (a row, never a browser value). */
    private function paid(Team $team, int $n): void
    {
        TeamLicence::updateOrCreate(['team_id' => $team->id], ['purchased_seats' => $n, 'payment_status' => 'active']);
    }

    public function test_the_shipped_default_is_one_free_licence_and_it_is_decided_in_one_place(): void
    {
        $this->assertSame(1, (int) (require base_path('config/licensing.php'))['included_seats'], 'the config default (no env) is one');
        [, $team] = $this->company();
        $this->assertSame(1, app(SeatLedger::class)->freeAllowance());
        $this->assertSame(1, app(SeatLedger::class)->summary($team)['included']);

        config(['licensing.included_seats' => 3]);   // the whole cutover and rollback is this one setting
        $this->assertSame(3, app(SeatLedger::class)->summary($team)['included']);
    }

    public function test_a_new_company_gets_one_free_licence_and_a_second_invitation_is_refused_without_a_paid_one(): void
    {
        [$owner, $team] = $this->company();

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame([1, 1, 0, 0], [$s['capacity'], $s['people'], $s['reserved'], $s['available']]);

        try {
            $this->invite($owner, $team, 'second@example.test');
            $this->fail('the second person needs a paid licence');
        } catch (SeatLimitReached $e) {
            $this->assertStringContainsString('1 licence', $e->errors()['email'][0]);
        }

        $this->assertSame(0, $team->teamInvitations()->count());
        $this->paid($team, 1);
        $this->invite($owner, $team, 'second@example.test');
        $this->assertSame(1, $team->teamInvitations()->count());
    }

    public function test_the_free_licence_cannot_be_claimed_again_by_deleting_or_recreating_the_row(): void
    {
        [$owner, $team] = $this->company();
        app(SeatLedger::class)->licenceFor($team);
        TeamLicence::where('team_id', $team->id)->delete();   // a missing row means "the free allowance", never more

        $this->assertSame(1, app(SeatLedger::class)->summary($team)['capacity']);
        $this->assertSame(1, TeamLicence::where('team_id', $team->id)->count(), 'one ledger row per company');
        $this->expectException(QueryException::class);
        TeamLicence::create(['team_id' => $team->id]);
    }

    public function test_one_companys_licences_never_pay_for_another_companys_people(): void
    {
        [$ownerA, $teamA] = $this->company();
        [$ownerB, $teamB] = $this->company();
        $this->paid($teamA, 3);

        $this->assertSame(4, app(SeatLedger::class)->summary($teamA)['capacity']);
        $this->assertSame(1, app(SeatLedger::class)->summary($teamB)['capacity']);

        $this->expectException(SeatLimitReached::class);
        $this->invite($ownerB, $teamB, 'x@example.test');
    }

    public function test_a_price_is_shown_only_from_configuration(): void
    {
        [, $team] = $this->company();
        $this->assertNull(app(SeatLedger::class)->summary($team)['price']);

        config(['licensing.price.amount_minor_units' => 9900, 'licensing.price.annual_amount_minor_units' => 99000, 'licensing.price.currency' => 'ZAR']);
        $price = app(SeatLedger::class)->summary($team)['price'];
        $this->assertSame([9900, 99000, 'ZAR'], [$price['amount_minor_units'], $price['annual_amount_minor_units'], $price['currency']]);
        $this->assertSame('99.00 ZAR', SeatLedger::formatMoney(9900, 'ZAR'));
        $this->assertNull(SeatLedger::formatMoney(null, 'ZAR'));
    }

    public function test_the_owner_takes_the_free_licence_and_two_paid_licences_allow_two_people_to_be_invited(): void
    {
        [$owner, $team] = $this->company();
        $this->paid($team, 2);

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
        $this->paid($team, 2);
        $this->invite($owner, $team, 'a@example.test');
        $this->invite($owner, $team, 'b@example.test');

        try {
            $this->invite($owner, $team, 'c@example.test');
            $this->fail('expected the seat limit');
        } catch (SeatLimitReached $e) {
            $message = $e->errors()['email'][0];
            $this->assertStringContainsString('3 licences', $message);
            $this->assertStringContainsString('needs a paid licence', $message);
            $this->assertStringContainsString('nothing has been charged', $message);
            $this->assertStringContainsString('nobody has been removed', $message);
        }
        $this->assertSame(2, $team->teamInvitations()->count(), 'the refused invitation was not created');
        $this->assertSame(1, TeamLicenceEvent::where('team_id', $team->id)->where('type', 'blocked')->count());
    }

    public function test_accepting_an_invitation_does_not_count_the_seat_twice(): void
    {
        [$owner, $team] = $this->company();
        $this->paid($team, 2);
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
        $this->paid($team, 2);
        $this->invite($owner, $team, 'a@example.test');
        $this->invite($owner, $team, 'b@example.test');
        $team->teamInvitations()->first()->delete();

        $this->invite($owner, $team, 'c@example.test');
        $this->assertSame(2, $team->teamInvitations()->count());
    }

    public function test_personal_workspaces_are_not_licensed_and_cannot_take_other_people(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->assertFalse(app(SeatLedger::class)->summary($team)['applies']);

        // Seat limits skip a personal workspace, so it must not be possible to turn one into an unlicensed company.
        $this->expectException(AuthorizationException::class);
        $this->invite($user, $team, 'a@example.test');
    }

    public function test_a_personal_workspace_adds_nobody_either_way(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->can('addTeamMember', $user->currentTeam));
        $this->assertSame(0, $user->currentTeam->teamInvitations()->count());
        $this->assertFalse($other->belongsToTeam($user->currentTeam));
    }

    public function test_a_company_over_capacity_keeps_everyone_and_only_new_additions_are_blocked(): void
    {
        [$owner, $team] = $this->company();
        $this->paid($team, 2);
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
        TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => 4, 'payment_status' => 'active']);
        foreach (['a', 'b', 'c', 'd'] as $n) {
            $this->invite($owner, $team, "$n@example.test");
        }

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame(5, $s['capacity']);
        $this->assertNull($s['price'], 'no price is shown until the owner approves one');
        $this->assertFalse($s['can_buy']);
    }

    public function test_buying_is_offered_only_when_finance_is_connected_and_no_price_is_invented(): void
    {
        [, $team] = $this->company();

        $s = app(SeatLedger::class)->summary($team);
        $this->assertFalse($s['can_buy'], 'finance is not connected');
        $this->assertNull($s['price']);

        config(['licensing.finance_connected' => true]);
        $s = app(SeatLedger::class)->summary($team);
        $this->assertTrue($s['can_buy']);
        $this->assertNull($s['price'], 'InfoDot never states a price: Finance owns it');
    }

    public function test_the_check_and_the_change_run_in_one_transaction_that_locks_the_company(): void
    {
        [$owner, $team] = $this->company();
        $this->paid($team, 1);
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
        $this->actingAs($owner)->get('/teams/'.$team->id)->assertOk()->assertSee('data-seats', false)->assertSee('3 of 1', false)->assertDontSee('per person per month');
    }

    public function test_the_licences_panel_shows_a_price_only_when_one_is_configured(): void
    {
        [$owner, $team] = $this->company();
        config(['licensing.price.amount_minor_units' => 9900, 'licensing.price.annual_amount_minor_units' => 99000, 'licensing.price.currency' => 'ZAR']);

        $this->actingAs($owner)->get('/teams/'.$team->id)->assertOk()->assertSee('99.00 ZAR per person per month, 990.00 ZAR per person per year, excluding VAT');
    }

    public function test_removing_a_person_is_audited_and_keeps_their_account(): void
    {
        [$owner, $team] = $this->company();
        $m = User::factory()->create();
        $team->users()->attach($m, ['role' => 'member']);

        $this->actingAs($owner);
        app(RemoveTeamMember::class)->remove($owner, $team, $m);

        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'left']);
        $this->assertNotNull(User::find($m->id));
    }

    public function test_invitations_and_joins_leave_an_audit_trail(): void
    {
        [$owner, $team] = $this->company();
        $this->paid($team, 1);
        $this->invite($owner, $team, 'a@example.test');

        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'invited', 'subject' => 'a@example.test', 'actor_id' => $owner->id]);
    }
}
