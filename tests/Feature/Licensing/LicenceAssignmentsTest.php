<?php

namespace Tests\Feature\Licensing;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Teams\TransferTeamOwnership;
use App\Models\LicenceAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\LicenceLifecycle;
use App\Services\Licensing\LicenceRefused;
use App\Services\Licensing\SeatLedger;
use App\Services\Platforms\AccessDecision;
use App\Support\EcosystemHandoff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

/** R-53 D5, D6: who holds a licence, the owner's release and reassign, the licensed check on platform access, and the free licence moving with ownership. */
class LicenceAssignmentsTest extends LicenceTestCase
{
    private function svc(): LicenceAssignments
    {
        return app(LicenceAssignments::class);
    }

    private function lapse(Team $team, User $user): void
    {
        LicenceAssignment::updateOrCreate(['team_id' => $team->id, 'user_id' => $user->id], ['state' => 'lapsed', 'released_at' => now()]);
    }

    public function test_a_lapsed_member_loses_the_companys_platforms_but_keeps_their_account_and_place(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');
        $member = $this->join($team);
        $this->assertTrue($this->access()->decideFor($member, $team, 'forms')->allowed);

        $this->lapse($team, $member);

        $decision = $this->access()->decideFor($member->fresh(), $team->fresh(), 'forms');
        $this->assertSame([false, AccessDecision::NOT_LICENSED], [$decision->allowed, $decision->reason]);
        $this->assertTrue($member->fresh()->belongsToTeam($team->fresh()), 'still in the company');
        $this->assertNull($member->fresh()->suspended_at, 'and nothing happened to the account');

        // Their own personal workspace is untouched by the company's licences.
        $personal = Team::where('user_id', $member->id)->where('personal_team', true)->firstOrFail();
        $this->assertNotSame(AccessDecision::NOT_LICENSED, $this->access()->decideFor($member->fresh(), $personal, 'forms')->reason);
    }

    public function test_the_owner_and_administrators_are_licensed_whatever_a_row_says(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');
        $admin = $this->join($team, 'admin');
        $this->lapse($team, $admin);
        $this->lapse($team, $team->owner);

        foreach ([$team->owner, $admin] as $person) {
            $this->assertTrue($this->svc()->isLicensed($team->fresh(), $person->fresh()));
            $this->assertTrue($this->access()->decideFor($person->fresh(), $team->fresh(), 'forms')->allowed);
        }
    }

    public function test_the_redeem_answer_marks_a_lapsed_members_company_as_not_entitled(): void
    {
        config(['ecosystem.service_secrets' => ['forms' => 'forms-secret-for-tests']]);
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');
        $member = $this->join($team);
        $other = $this->company('Second Co');
        $other->users()->attach($member, ['role' => 'member']);
        $this->activatePlatforms($other, 'forms');
        $this->lapse($team, $member);

        $token = $member->createToken(EcosystemHandoff::tokenName('forms'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;
        $answer = $this->withHeaders(['Authorization' => 'Bearer forms-secret-for-tests'])->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'forms'])->assertOk();

        $teams = collect($answer->json('teams'))->keyBy('uuid');
        $this->assertSame([false, 'not_licensed'], [$teams[$team->uuid]['entitled'], $teams[$team->uuid]['access']], 'the lapsed company is not entitled for this person');
        $this->assertTrue($teams[$other->uuid]['entitled'], 'a company where they hold a licence still is: a licence is one person in one company');
    }

    public function test_a_person_whose_only_company_lapsed_them_is_refused_like_any_other_bad_code(): void
    {
        config(['ecosystem.service_secrets' => ['forms' => 'forms-secret-for-tests']]);
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');
        $member = $this->join($team);
        $member->forceFill(['current_team_id' => $team->id])->save();
        Team::where('user_id', $member->id)->where('personal_team', true)->delete();
        $this->lapse($team, $member);

        $token = $member->createToken(EcosystemHandoff::tokenName('forms'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer forms-secret-for-tests'])->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'forms'])->assertStatus(403);
    }

    public function test_joining_through_the_real_path_records_an_active_assignment_and_rejoining_starts_fresh(): void
    {
        $team = $this->company();
        $this->paid($team, 1);
        $user = User::factory()->create(['email' => 'new@example.test']);
        $this->actingAs($team->owner);

        app(InviteTeamMember::class)->invite($team->owner, $team, 'new@example.test', 'member');
        app(AddTeamMember::class)->add($team->owner, $team, 'new@example.test', 'member');
        $team->teamInvitations()->delete();

        $row = LicenceAssignment::where(['team_id' => $team->id, 'user_id' => $user->id])->sole();
        $this->assertSame('active', $row->state);
        $this->assertNotNull($row->batch_id, 'it is recorded against the paid batch that has a licence free');
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'joined', 'subject' => 'new@example.test']);

        // Removal ends the membership and the record; coming back later is a new membership with a new licence.
        app(RemoveTeamMember::class)->remove($team->owner, $team->fresh(), $user);
        $this->assertSame(0, LicenceAssignment::where(['team_id' => $team->id, 'user_id' => $user->id])->count());
        $this->assertNotNull(User::find($user->id));
    }

    public function test_the_owner_releases_assigns_and_reassigns_and_each_is_audited(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        $a = $this->join($team);
        $b = $this->join($team);
        $c = $this->join($team);   // three members on a capacity of three: the owner and two paid
        $this->assertSame(3, app(SeatLedger::class)->summary($team)['capacity']);
        $this->svc()->release($team, $team->owner, $c);   // company over by one, but release is the owner's call: c now has none

        $this->assertFalse($this->svc()->isLicensed($team->fresh(), $c));
        $this->assertSame('released', LicenceAssignment::where(['team_id' => $team->id, 'user_id' => $c->id])->value('state'));
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'licence_released', 'subject' => $c->email]);

        // Reassign: take it from a and give it to c in one step.
        $this->svc()->reassign($team, $team->owner, $a, $c);
        $this->assertFalse($this->svc()->isLicensed($team->fresh(), $a));
        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $c));
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'licence_assigned', 'subject' => $c->email]);

        // Nothing is free now: assigning a again is refused with an explanation, and changes nothing.
        try {
            $this->svc()->assign($team, $team->owner, $a);
            $this->fail('there is no licence free');
        } catch (LicenceRefused $e) {
            $this->assertStringContainsString('no licence free', $e->getMessage());
        }
        $this->assertFalse($this->svc()->isLicensed($team->fresh(), $a));
        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $b));
        $this->assertSame(3, $team->users()->count());
    }

    public function test_release_assign_and_reassign_are_owner_only_and_never_cross_companies_or_touch_administrators(): void
    {
        $team = $this->company();
        $admin = $this->join($team, 'admin');
        $member = $this->join($team);
        $other = $this->company('Other Co');
        $stranger = $this->join($other);

        foreach ([$admin, $member, $other->owner] as $actor) {
            try {
                $this->svc()->release($team, $actor, $member);
                $this->fail('only the owner');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach ([[$team, $team->owner, $admin, 'Administrators always keep'], [$team, $team->owner, $stranger, 'not in this company'], [$other, $team->owner, $stranger, null]] as [$t, $actor, $target, $message]) {
            try {
                $this->svc()->release($t, $actor, $target);
                $this->fail('refused');
            } catch (LicenceRefused $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            } catch (AuthorizationException) {
                $this->assertNull($message, 'the other company is not the actor\'s to change');
            }
        }

        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $member));
        $this->assertTrue($this->svc()->isLicensed($other->fresh(), $stranger));
    }

    public function test_buying_licences_brings_lapsed_people_back_oldest_first_but_not_those_the_owner_released(): void
    {
        $team = $this->company();
        $first = $this->join($team);
        $second = $this->join($team);
        $third = $this->join($team);
        $released = $this->join($team);
        foreach ([$first, $second, $third] as $m) {
            $this->lapse($team, $m);
        }
        $this->svc()->release($team, $team->owner, $released);

        $this->paid($team, 2);   // room for two of the three lapsed

        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $first));
        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $second));
        $this->assertFalse($this->svc()->isLicensed($team->fresh(), $third), 'only as many as there is room for');
        $this->assertFalse($this->svc()->isLicensed($team->fresh(), $released), 'a licence the owner took back stays taken until the owner gives it');
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'licence_reactivated', 'subject' => $first->email]);
    }

    public function test_removing_someone_clears_a_leftover_grace_when_there_is_room_again(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 1);
        $a = $this->join($team);
        $this->finance($this->licenceEvent($batch, 'cancelled'))->assertOk();
        $this->travel(32)->days();
        $this->artisan('licence:sweep')->assertSuccessful();
        $this->assertNotNull($team->licence()->first()->grace_until);

        $this->actingAs($team->owner);
        app(RemoveTeamMember::class)->remove($team->owner, $team->fresh(), $a);

        $this->assertNull($team->licence()->first()->grace_until, 'under capacity again: the grace is cleared');
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'licence_grace_cleared']);
    }

    public function test_a_company_over_with_only_administrators_to_lapse_loses_nobody_and_is_not_nagged(): void
    {
        $team = $this->company();
        $this->join($team, 'admin');
        $this->join($team, 'admin');   // owner + 2 admins on 1 licence

        $r = app(LicenceLifecycle::class)->rebalance($team);
        $r2 = app(LicenceLifecycle::class)->rebalance($team, true);

        $this->assertSame([0, false], [$r['lapsed'], $r['grace_started']]);
        $this->assertSame(0, $r2['lapsed']);
        $this->assertNull($team->licence()->first()->grace_until);
        $this->assertSame(2, app(SeatLedger::class)->summary($team)['over_by']);
    }

    public function test_ownership_transfer_moves_the_free_licence_and_the_old_owner_needs_a_paid_one(): void
    {
        $team = $this->company();
        $this->paid($team, 1);
        $keep = $this->join($team);            // holds the paid licence
        $released = $this->join($team);        // released: has none
        $this->svc()->release($team, $team->owner, $released);
        $oldOwner = $team->owner;
        $oldOwner->forceFill(['password' => Hash::make('password')])->save();
        $this->actingAs($oldOwner);

        app(TransferTeamOwnership::class)->transfer($oldOwner, $team, $released);

        $team = $team->fresh();
        $this->assertSame($released->id, $team->user_id);
        $this->assertTrue($this->svc()->isLicensed($team, $released->fresh()), 'the new owner holds the free licence');
        $this->assertSame(0, LicenceAssignment::where(['team_id' => $team->id, 'user_id' => $released->id])->count(), 'the owner is implicit');
        $this->assertFalse($this->svc()->isLicensed($team, $oldOwner->fresh()), 'the person who was the owner now needs a paid licence: the company had none free');
        $this->assertTrue($this->svc()->isLicensed($team, $keep->fresh()));
        $this->assertTrue($team->fresh()->users->contains($oldOwner), 'and they are still in the company with their account and content');
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'ownership_licence_swapped']);
    }

    public function test_ownership_transfer_with_a_licence_to_spare_costs_nobody_anything_and_an_admin_is_never_lapsed(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        $admin = $this->join($team, 'admin');
        $oldOwner = $team->owner;
        $this->actingAs($oldOwner);

        app(TransferTeamOwnership::class)->transfer($oldOwner, $team, $admin);

        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $oldOwner->fresh()));
        $this->assertTrue($this->svc()->isLicensed($team->fresh(), $admin->fresh()));

        // The lapsed route: the new owner had none, the company has none free, and the old owner becomes an administrator: never lapsed.
        $team2 = $this->company('Second');
        $adminOld = $team2->owner;
        $lapsedMember = $this->join($team2);
        $this->lapse($team2, $lapsedMember);
        $this->actingAs($adminOld);
        app(TransferTeamOwnership::class)->transfer($adminOld, $team2, $lapsedMember);
        $this->assertTrue($this->svc()->isLicensed($team2->fresh(), $lapsedMember->fresh()));
        $this->assertFalse($this->svc()->isLicensed($team2->fresh(), $adminOld->fresh()), 'a plain member after the swap, with no licence free');
    }

    public function test_the_licensed_count_ignores_lapsed_people_and_other_companies(): void
    {
        $team = $this->company();
        $a = $this->join($team);
        $this->join($team);
        $this->lapse($team, $a);
        $other = $this->company('Other');
        $this->lapse($other, $a);   // a row in another company says nothing about this one

        $this->assertSame(2, $this->svc()->licensedCount($team));
        $this->assertSame(3, app(SeatLedger::class)->summary($team)['people'], 'a lapsed person still takes a place until they leave, so room goes to them first');
    }
}
