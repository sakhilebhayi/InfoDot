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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** R-53 D7: companies that existed before the one-free-licence rule are frozen at their headcount for 90 days. Dry run by default, nobody removed, reversible. */
class GrandfatheringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['licensing.included_seats' => 1, 'licensing.grandfather_days' => 90, 'licensing.pending_invitations_reserve_a_seat' => true]);
    }

    /** A company with $members people in it plus the owner, and $invited live invitations. */
    private function company(int $members = 0, int $invited = 0): Team
    {
        $team = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        foreach (User::factory()->count($members)->create() as $u) {
            $team->users()->attach($u, ['role' => 'member']);
        }
        for ($i = 0; $i < $invited; $i++) {
            $team->teamInvitations()->create(['email' => "pending{$i}-{$team->id}@example.test", 'role' => 'member']);
        }

        return $team;
    }

    public function test_a_dry_run_is_the_default_and_writes_nothing(): void
    {
        $team = $this->company(2, 1);

        $this->artisan('licence:grandfather')->expectsOutputToContain('DRY RUN: 1 company(ies) would be frozen')->assertSuccessful();

        $this->assertSame(0, TeamLicence::count(), 'a dry run does not even create the ledger row');
        $this->assertSame(0, TeamLicenceEvent::count());
        $this->assertSame(1, app(SeatLedger::class)->summary($team)['capacity'], 'and the company is still on the plain free allowance');
    }

    public function test_apply_freezes_the_headcount_with_owner_members_and_live_invitations(): void
    {
        $team = $this->company(2, 1);   // owner + 2 members + 1 live invitation = 4
        $team->teamInvitations()->create(['email' => 'old@example.test', 'role' => 'member'])->forceFill(['expires_at' => now()->subDay()])->save();   // expired: holds nothing

        $this->artisan('licence:grandfather', ['--apply' => true])->expectsOutputToContain('1 company(ies) frozen for 90 days')->assertSuccessful();

        $licence = TeamLicence::where('team_id', $team->id)->sole();
        $this->assertSame(4, $licence->included_seats);
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $licence->grandfathered_until->timestamp, 5);
        $this->assertSame(['included_seats' => null, 'grandfathered_until' => null], array_intersect_key($licence->grandfather_snapshot, ['included_seats' => 1, 'grandfathered_until' => 1]));

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame([4, 3, 1, 0, true], [$s['capacity'], $s['people'], $s['reserved'], $s['available'], $s['grandfathered']]);
        $this->assertSame(2, $team->users()->count(), 'nobody was removed');
        $this->assertSame(1, TeamLicenceEvent::where('team_id', $team->id)->where('type', 'grandfathered')->count());
        $this->assertSame(4, TeamLicenceEvent::where('type', 'grandfathered')->value('details')['headcount']);
    }

    public function test_a_frozen_company_keeps_what_it_has_but_cannot_add_above_it_without_a_paid_licence(): void
    {
        $team = $this->company(2, 1);
        $this->artisan('licence:grandfather', ['--apply' => true])->assertSuccessful();
        $owner = $team->owner;

        try {
            app(InviteTeamMember::class)->invite($owner, $team, 'extra@example.test', 'member');
            $this->fail('a person above the frozen headcount needs a paid licence');
        } catch (SeatLimitReached $e) {
            $this->assertStringContainsString('keeps its current people free until', $e->errors()['email'][0]);
        }

        // The invitation that was live when the company was frozen can still be accepted: it holds its own reservation.
        $invited = $team->teamInvitations()->first();
        User::factory()->create(['email' => $invited->email]);
        app(AddTeamMember::class)->add($owner, $team, $invited->email, 'member');
        $invited->delete();   // Jetstream deletes the invitation once it is accepted
        $this->assertSame(3, $team->fresh()->users()->count());

        // One paid licence is exactly one more person.
        TeamLicence::where('team_id', $team->id)->update(['purchased_seats' => 1, 'payment_status' => 'active']);
        app(InviteTeamMember::class)->invite($owner, $team, 'extra@example.test', 'member');
        $this->assertSame(1, $team->teamInvitations()->where('email', 'extra@example.test')->count());
    }

    public function test_apply_is_idempotent(): void
    {
        $team = $this->company(1);
        $this->artisan('licence:grandfather', ['--apply' => true])->assertSuccessful();
        $first = TeamLicence::where('team_id', $team->id)->sole();
        $until = $first->grandfathered_until->timestamp;

        $this->artisan('licence:grandfather', ['--apply' => true, '--days' => 400])->expectsOutputToContain('0 company(ies) frozen')->assertSuccessful();

        $again = TeamLicence::where('team_id', $team->id)->sole();
        $this->assertSame($until, $again->grandfathered_until->timestamp, 'a second run does not extend or restart the clock');
        $this->assertSame(2, $again->included_seats);
        $this->assertSame(1, TeamLicenceEvent::where('type', 'grandfathered')->count());
    }

    public function test_the_number_of_days_can_be_chosen(): void
    {
        $team = $this->company(1);
        $this->artisan('licence:grandfather', ['--apply' => true, '--days' => 30])->assertSuccessful();

        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, TeamLicence::where('team_id', $team->id)->value('grandfathered_until') ? TeamLicence::where('team_id', $team->id)->sole()->grandfathered_until->timestamp : 0, 5);
        $this->artisan('licence:grandfather', ['--days' => 0])->assertFailed();
    }

    public function test_companies_within_the_free_allowance_personal_workspaces_and_manual_overrides_are_left_alone(): void
    {
        $solo = $this->company(0, 0);
        $personal = User::factory()->withPersonalTeam()->create()->currentTeam;
        $manual = $this->company(3);
        TeamLicence::create(['team_id' => $manual->id, 'included_seats' => 10]);

        $this->artisan('licence:grandfather', ['--apply' => true])->expectsOutputToContain('0 company(ies) frozen')->assertSuccessful();

        $this->assertNull(TeamLicence::where('team_id', $solo->id)->value('grandfathered_until'));
        $this->assertSame(0, TeamLicence::where('team_id', $personal->id)->count());
        $this->assertSame(10, TeamLicence::where('team_id', $manual->id)->value('included_seats'), 'a deliberate manual override is never overwritten');
    }

    public function test_what_was_already_bought_stays_on_top_of_the_frozen_part(): void
    {
        $team = $this->company(4);   // 5 people
        TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => 2, 'payment_status' => 'active']);

        $this->artisan('licence:grandfather', ['--apply' => true])->assertSuccessful();

        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame([3, 2, 5, 0], [$s['included'], $s['purchased'], $s['capacity'], $s['available']], 'capacity is exactly the headcount, not more');
    }

    public function test_the_freeze_only_touches_the_companies_asked_for(): void
    {
        $a = $this->company(2);
        $b = $this->company(2);

        $this->artisan('licence:grandfather', ['--apply' => true, '--team' => [$a->id]])->assertSuccessful();

        $this->assertNotNull(TeamLicence::where('team_id', $a->id)->value('grandfathered_until'));
        $this->assertSame(0, TeamLicence::where('team_id', $b->id)->count(), 'another company is untouched');
        $this->assertSame(0, TeamLicenceEvent::where('team_id', $b->id)->count());
    }

    public function test_rollback_is_a_report_until_apply_and_then_restores_the_previous_row_exactly(): void
    {
        $team = $this->company(2);
        $other = $this->company(1);
        $this->artisan('licence:grandfather', ['--apply' => true])->assertSuccessful();

        $this->artisan('licence:grandfather', ['--rollback' => true])->expectsOutputToContain('DRY RUN: 2 company row(s) would be restored')->assertSuccessful();
        $this->assertSame(3, TeamLicence::where('team_id', $team->id)->value('included_seats'), 'a rollback report changes nothing');

        $this->artisan('licence:grandfather', ['--rollback' => true, '--apply' => true])->expectsOutputToContain('2 company row(s) restored')->assertSuccessful();

        foreach ([$team, $other] as $t) {
            $row = TeamLicence::where('team_id', $t->id)->sole();
            $this->assertNull($row->included_seats);
            $this->assertNull($row->grandfathered_until);
            $this->assertNull($row->grandfather_snapshot);
        }
        $this->assertSame(2, $team->users()->count(), 'nobody was added or removed');
        $this->assertSame(1, TeamLicenceEvent::where('team_id', $team->id)->where('type', 'grandfather_rolled_back')->count());

        $this->artisan('licence:grandfather', ['--rollback' => true, '--apply' => true])->expectsOutputToContain('0 company row(s) restored')->assertSuccessful();

        // And it can be frozen again afterwards.
        $this->artisan('licence:grandfather', ['--apply' => true])->expectsOutputToContain('2 company(ies) frozen')->assertSuccessful();
    }

    public function test_when_the_transition_ends_the_frozen_people_are_no_longer_free_but_nobody_is_removed(): void
    {
        $team = $this->company(2);
        $this->artisan('licence:grandfather', ['--apply' => true, '--days' => 1])->assertSuccessful();
        $this->assertSame(3, app(SeatLedger::class)->summary($team)['capacity']);

        $this->travel(2)->days();

        $s = app(SeatLedger::class)->summary($team->fresh());
        $this->assertSame([1, 3, 2, false], [$s['capacity'], $s['people'], $s['over_by'], $s['grandfathered']]);
        $this->assertSame(2, $team->users()->count());
    }

    public function test_the_whole_cutover_can_be_reversed_by_one_setting(): void
    {
        $team = $this->company();
        config(['licensing.included_seats' => 3]);
        $this->assertSame(3, app(SeatLedger::class)->summary($team)['capacity']);
        config(['licensing.included_seats' => 1]);
        $this->assertSame(1, app(SeatLedger::class)->summary($team)['capacity']);
    }
}
