<?php

namespace Tests\Feature;

use App\Actions\Jetstream\CreateTeam;
use App\Actions\Teams\TransferTeamOwnership;
use App\Livewire\Teams\TransferOwnershipForm;
use App\Models\Attachment;
use App\Models\Team;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-49. Deleting an account used to delete every business the person owned, for every member, without a word. Now
 * the account stays while the person owns a business other people are members of, the message names the businesses
 * and the way out, and the way out exists: the owner can hand a business to one of its members.
 */
class OwnershipTransferAndAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name): User
    {
        return User::factory()->withPersonalTeam()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function business(User $owner, string $name, array $members = []): Team
    {
        $team = app(CreateTeam::class)->create($owner, ['name' => $name, 'visibility' => 'private']);
        foreach ($members as $role => $member) {
            $team->users()->attach($member, ['role' => is_string($role) ? $role : 'member']);
        }

        return $team->fresh();
    }

    // ---- deleting an account --------------------------------------------------------------------------------------

    public function test_an_account_that_owns_a_business_with_other_members_is_not_deleted_and_the_message_names_it(): void
    {
        $owner = $this->person('Owner');
        $member = $this->person('Member');
        $first = $this->business($owner, 'Acme Boreholes', [$member]);
        $second = $this->business($owner, 'Second Yard', ['admin' => $member]);
        $alone = $this->business($owner, 'Only Me Trading');

        $this->actingAs($owner);
        Livewire::test(DeleteUserForm::class)->set('password', 'password')->call('deleteUser')
            ->assertHasErrors(['account'])
            ->assertSee('“Acme Boreholes” and “Second Yard”')
            ->assertSee('transfer its ownership to a member or delete the company');

        $this->assertNotNull($owner->fresh(), 'the account stays');
        $this->assertAuthenticatedAs($owner);
        foreach ([$first, $second, $alone] as $team) {
            $this->assertNotNull(Team::find($team->id), $team->name.' stays, the solo business included: nothing is half-deleted');
        }
        $this->assertTrue($member->fresh()->belongsToTeam($first->fresh()));
    }

    public function test_the_delete_form_explains_and_links_instead_of_offering_the_button(): void
    {
        $owner = $this->person('Owner');
        $team = $this->business($owner, 'Acme Boreholes', [$this->person('Member'), $this->person('Second Member')]);

        $html = $this->actingAs($owner)->get('/user/profile/edit')->assertOk()
            ->assertSee('Your account cannot be deleted yet')
            ->assertSee('Acme Boreholes')
            ->assertSee('2 other members')
            ->getContent();

        $this->assertStringContainsString('href="'.route('teams.show', $team->id).'#transfer"', (string) $html);
        $this->assertStringNotContainsString('wire:click="confirmUserDeletion"', (string) $html);

        // Someone who owns nothing shared still gets the button.
        $this->actingAs($this->person('Free'))->get('/user/profile/edit')->assertOk()
            ->assertDontSee('Your account cannot be deleted yet')->assertSee('wire:click="confirmUserDeletion"', false);
    }

    public function test_a_refused_deletion_touches_nothing_even_when_called_directly(): void
    {
        $owner = $this->person('Owner');
        $team = $this->business($owner, 'Acme Boreholes', [$this->person('Member')]);

        try {
            app(DeletesUsers::class)->delete($owner->fresh());
            $this->fail('The account was deleted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('“Acme Boreholes”', $e->errors()['account'][0]);
            $this->assertStringContainsString('Transfer its ownership to one of them, or delete the company', $e->errors()['account'][0]);
        }

        $this->assertNotNull($owner->fresh());
        $this->assertNotNull(Team::find($team->id));
        $this->assertNotNull($owner->fresh()->personalTeam());
    }

    public function test_a_business_with_no_other_member_still_goes_with_the_account_and_its_files_are_removed(): void
    {
        Storage::fake('local');
        $owner = $this->person('Owner');
        $team = $this->business($owner, 'Only Me Trading');
        $path = 'attachments/test/'.Str::random(12).'-logo.jpg';
        Storage::disk('local')->put($path, 'file body');
        (new Attachment)->forceFill([
            'user_id' => $owner->id, 'attachable_type' => $team->getMorphClass(), 'attachable_id' => $team->id,
            'kind' => Attachment::IMAGE, 'path' => $path, 'name' => 'logo.jpg', 'mime' => 'image/jpeg', 'size' => 9,
        ])->save();

        $this->actingAs($owner);
        Livewire::test(DeleteUserForm::class)->set('password', 'password')->call('deleteUser')->assertHasNoErrors();

        $this->assertNull(User::find($owner->id));
        $this->assertNull(Team::find($team->id));
        // The files go on the daily prune, once the grace period for a row whose owner record is gone has passed.
        $this->travel(2)->days();
        $this->artisan('attachments:prune')->assertSuccessful();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_after_transferring_or_emptying_the_business_the_account_can_be_deleted_and_the_business_survives(): void
    {
        $owner = $this->person('Owner');
        $member = $this->person('Member');
        $handedOn = $this->business($owner, 'Handed On', [$member]);
        $emptied = $this->business($owner, 'Emptied', [$member]);

        app(TransferTeamOwnership::class)->transfer($owner, $handedOn, $member);
        $emptied->users()->detach($member);

        app(DeletesUsers::class)->delete($owner->fresh());

        $this->assertNull(User::find($owner->id));
        $this->assertSame($member->id, Team::findOrFail($handedOn->id)->user_id, 'the business lives on under its new owner');
        $this->assertNull(Team::find($emptied->id));
    }

    // ---- transferring ownership -----------------------------------------------------------------------------------

    public function test_the_owner_hands_the_business_to_a_member_and_the_two_swap_roles(): void
    {
        $owner = $this->person('Owner');
        $admin = $this->person('Admin Person');
        $member = $this->person('Member Person');
        $team = $this->business($owner, 'Acme Boreholes', ['admin' => $admin, 'member' => $member]);
        $seatsBefore = $team->users()->count() + 1;

        $this->actingAs($owner);
        Livewire::test(TransferOwnershipForm::class, ['team' => $team])
            ->assertSee('Admin Person (Administrator)')->assertSee('Member Person (Member)')
            ->set('newOwner', (string) $admin->id)->set('password', 'password')
            ->call('transferOwnership')
            ->assertHasNoErrors()
            ->assertRedirect(route('teams.show', $team->id));

        $team = $team->fresh();
        $this->assertSame($admin->id, $team->user_id);
        $this->assertTrue($admin->fresh()->ownsTeam($team));
        $this->assertFalse($team->users()->whereKey($admin->id)->exists(), 'the owner is not also a member row');
        $this->assertFalse($owner->fresh()->ownsTeam($team));
        $this->assertSame('admin', $team->users()->whereKey($owner->id)->firstOrFail()->membership->role, 'the former owner takes the role the new owner held');
        $this->assertSame('member', $team->users()->whereKey($member->id)->firstOrFail()->membership->role, 'nobody else changes');
        $this->assertSame($seatsBefore, $team->users()->count() + 1, 'the same people hold the same number of seats');

        // The audit trail, and one notice each, written for the side the reader is on.
        $event = TeamLicenceEvent::where('team_id', $team->id)->where('type', 'ownership_transferred')->firstOrFail();
        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($admin->email, $event->subject);
        $this->assertSame(['from' => $owner->id, 'to' => $admin->id, 'former_owner_role' => 'admin'], $event->details);

        $toNew = UserNotice::where('user_id', $admin->id)->where('kind', UserNotice::BUSINESS_OWNERSHIP)->firstOrFail();
        $toOld = UserNotice::where('user_id', $owner->id)->where('kind', UserNotice::BUSINESS_OWNERSHIP)->firstOrFail();
        $this->assertSame('You are now the owner of “Acme Boreholes”. Seats, ownership and deleting the company are yours to decide.', app(InAppNotices::class)->describe($toNew)['text']);
        $this->assertSame('You transferred ownership of “Acme Boreholes”. You are still in the company as administrator.', app(InAppNotices::class)->describe($toOld)['text']);
        $this->assertSame(0, UserNotice::where('user_id', $member->id)->count());
        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('You are now the owner of');

        // The former owner still opens team settings, without the owner-only sections; the new owner has them.
        $this->actingAs($owner->fresh())->get(route('teams.show', $team->id))->assertOk()->assertDontSee('id="transfer"', false)->assertDontSee('id="delete"', false);
        $this->actingAs($admin->fresh())->get(route('teams.show', $team->id))->assertOk()->assertSee('id="transfer"', false)->assertSee('Ownership transferred to');
    }

    public function test_a_member_who_becomes_owner_leaves_the_former_owner_a_member(): void
    {
        $owner = $this->person('Owner');
        $member = $this->person('Member Person');
        $team = $this->business($owner, 'Acme Boreholes', ['member' => $member]);

        $this->assertSame('member', app(TransferTeamOwnership::class)->transfer($owner, $team, $member));
        $this->assertSame('member', $team->fresh()->users()->whereKey($owner->id)->firstOrFail()->membership->role);
    }

    public function test_the_password_must_be_right_and_a_person_must_be_chosen(): void
    {
        $owner = $this->person('Owner');
        $member = $this->person('Member Person');
        $team = $this->business($owner, 'Acme Boreholes', [$member]);

        $this->actingAs($owner);
        Livewire::test(TransferOwnershipForm::class, ['team' => $team])
            ->set('newOwner', (string) $member->id)->set('password', 'not-the-password')->call('transferOwnership')
            ->assertHasErrors(['password'])->assertSet('password', '');
        Livewire::test(TransferOwnershipForm::class, ['team' => $team])
            ->set('password', 'password')->call('transferOwnership')
            ->assertHasErrors(['new_owner']);

        $this->assertSame($owner->id, $team->fresh()->user_id);
        $this->assertSame(0, UserNotice::where('kind', UserNotice::BUSINESS_OWNERSHIP)->count());
    }

    public function test_only_someone_already_in_the_business_can_be_chosen(): void
    {
        $owner = $this->person('Owner');
        $team = $this->business($owner, 'Acme Boreholes', [$this->person('Member Person')]);
        $outsider = $this->person('Outsider');

        $this->actingAs($owner);
        Livewire::test(TransferOwnershipForm::class, ['team' => $team])
            ->assertDontSee('Outsider')
            ->set('newOwner', (string) $outsider->id)->set('password', 'password')->call('transferOwnership')
            ->assertHasErrors(['new_owner']);

        foreach ([$outsider, $owner] as $notAMember) {
            try {
                app(TransferTeamOwnership::class)->transfer($owner, $team, $notAMember);
                $this->fail('Ownership went to someone who is not a member.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('new_owner', $e->errors());
            }
        }
        $this->assertSame($owner->id, $team->fresh()->user_id);
    }

    public function test_only_the_owner_can_transfer_and_never_a_personal_workspace(): void
    {
        $owner = $this->person('Owner');
        $admin = $this->person('Admin Person');
        $member = $this->person('Member Person');
        $team = $this->business($owner, 'Acme Boreholes', ['admin' => $admin, 'member' => $member]);

        $this->actingAs($admin);
        Livewire::test(TransferOwnershipForm::class, ['team' => $team])->assertForbidden();
        $this->get(route('teams.show', $team->id))->assertOk()->assertDontSee('Transfer ownership');

        foreach ([[$admin, $team, $member], [$owner, $owner->personalTeam(), $member]] as [$actor, $subject, $target]) {
            try {
                app(TransferTeamOwnership::class)->transfer($actor, $subject, $target);
                $this->fail('A transfer that should be refused went through.');
            } catch (AuthorizationException) {
                // Refused.
            }
        }
        $this->assertSame($owner->id, $team->fresh()->user_id);
        $this->actingAs($owner)->get(route('teams.show', $owner->personalTeam()->id))->assertOk()->assertDontSee('Transfer ownership');
    }

    public function test_an_owner_with_nobody_else_is_told_how_to_get_someone_in(): void
    {
        $owner = $this->person('Owner');
        $team = $this->business($owner, 'Only Me Trading');

        $this->actingAs($owner)->get(route('teams.show', $team->id))->assertOk()
            ->assertSee('Transfer ownership')
            ->assertSee('You are the only person in Only Me Trading')
            ->assertDontSee('id="transfer_password"', false);
    }
}
