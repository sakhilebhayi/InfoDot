<?php

namespace Tests\Feature\Invitations;

use App\Actions\Jetstream\InviteManyTeamMembers;
use App\Livewire\Teams\TeamMemberManager;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamInviteLink;
use App\Models\TeamLicence;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** R-62 phase 4: several invitations at once, and a shareable link that is hashed, limited, expiring and revocable. */
class BulkAndLinkInvitesTest extends TestCase
{
    use RefreshDatabase;

    private function company(int $paidSeats = 10): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => $paidSeats, 'payment_status' => 'active']);

        return [$owner, $team];
    }

    public function test_the_list_is_split_on_any_separator_lowercased_and_deduplicated(): void
    {
        $this->assertSame(['a@x.co.za', 'b@x.co.za', 'c@x.co.za'], InviteManyTeamMembers::parse("A@x.co.za, b@x.co.za;\nc@x.co.za  a@x.co.za <B@x.co.za>"));
    }

    public function test_several_people_are_invited_and_each_gets_an_invitation(): void
    {
        Mail::fake();
        [$owner, $team] = $this->company();
        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $team])
            ->set('bulkEmails', "one@gmail.com\ntwo@gmail.com, three@gmail.com")
            ->set('bulkRole', 'member')
            ->call('inviteMany')
            ->assertSet('bulkEmails', '');

        $this->assertSame(3, TeamInvitation::where('team_id', $team->id)->count());
        Mail::assertSent(\Laravel\Jetstream\Mail\TeamInvitation::class, 3);
    }

    public function test_one_bad_address_does_not_stop_the_others_and_stays_in_the_box(): void
    {
        Mail::fake();
        [$owner, $team] = $this->company();
        $this->actingAs($owner);

        $c = Livewire::test(TeamMemberManager::class, ['team' => $team])
            ->set('bulkEmails', "good@gmail.com\nnot-an-email\nalso@gmail.com")
            ->call('inviteMany');

        $this->assertSame(2, TeamInvitation::where('team_id', $team->id)->count());
        $c->assertSet('bulkEmails', 'not-an-email');
        $this->assertSame('not-an-email', $c->get('bulkResult')['skipped'][0]['email']);
    }

    public function test_a_full_company_stops_the_batch_and_never_goes_over_its_licences(): void
    {
        Mail::fake();
        [$owner, $team] = $this->company(paidSeats: 2); // the owner's free seat + 2 paid = 3 people
        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $team])
            ->set('bulkEmails', "a@gmail.com\nb@gmail.com\nc@gmail.com\nd@gmail.com")
            ->call('inviteMany');

        $this->assertSame(2, TeamInvitation::where('team_id', $team->id)->count());
    }

    public function test_only_the_first_batch_size_is_used(): void
    {
        Mail::fake();
        [$owner, $team] = $this->company(paidSeats: 100);
        $list = collect(range(1, InviteManyTeamMembers::MAX + 5))->map(fn ($i) => "p{$i}@gmail.com")->implode("\n");

        $result = app(InviteManyTeamMembers::class)->invite($owner, $team, $list, 'member');

        $this->assertTrue($result['too_many']);
        $this->assertCount(InviteManyTeamMembers::MAX, $result['invited']);
    }

    public function test_a_plain_member_cannot_invite_in_bulk_or_make_a_link(): void
    {
        [$owner, $team] = $this->company();
        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'member']);
        $this->actingAs($member);

        Livewire::test(TeamMemberManager::class, ['team' => $team])->set('bulkEmails', 'x@gmail.com')->call('inviteMany')->assertForbidden();
        Livewire::test(TeamMemberManager::class, ['team' => $team])->call('createInviteLink')->assertForbidden();
        $this->assertSame(0, TeamInviteLink::count());
    }

    public function test_a_manager_may_bulk_invite_members_but_not_make_a_link_or_invite_admins(): void
    {
        Mail::fake();
        [$owner, $team] = $this->company();
        $manager = User::factory()->create();
        $team->users()->attach($manager, ['role' => 'manager']);
        $this->actingAs($manager);

        $c = Livewire::test(TeamMemberManager::class, ['team' => $team])->set('bulkEmails', 'x@gmail.com')->set('bulkRole', 'admin')->call('inviteMany');
        $this->assertSame(0, TeamInvitation::where('team_id', $team->id)->count());
        $this->assertNotEmpty($c->get('bulkResult')['skipped']);

        Livewire::test(TeamMemberManager::class, ['team' => $team])->call('createInviteLink')->assertForbidden();
    }

    public function test_a_link_is_stored_only_as_a_hash_and_is_shown_once(): void
    {
        [$owner, $team] = $this->company();
        $this->actingAs($owner);

        $c = Livewire::test(TeamMemberManager::class, ['team' => $team])->call('createInviteLink');
        $url = $c->get('newLink');
        $token = basename($url);

        $link = TeamInviteLink::firstOrFail();
        $this->assertNotSame($token, $link->token_hash);
        $this->assertSame(hash('sha256', $token), $link->token_hash);
        $this->assertSame('member', $link->role);
        $this->assertDatabaseMissing('team_invite_links', ['token_hash' => $token]);
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'link_created']);
    }

    public function test_the_join_page_shows_only_the_company_name_and_sends_a_guest_to_sign_in(): void
    {
        [$owner, $team] = $this->company();
        [, $token] = TeamInviteLink::issue($team, $owner);

        $this->get(route('join.show', $token))->assertOk()->assertSee($team->name)->assertSee('Sign in to join');
        $this->post(route('join.accept', $token))->assertRedirect(); // a guest cannot join
        $this->assertFalse($team->fresh()->hasUser(User::factory()->create()));
    }

    public function test_a_signed_in_person_joins_as_a_member_and_the_use_is_counted(): void
    {
        [$owner, $team] = $this->company();
        [$link, $token] = TeamInviteLink::issue($team, $owner);
        $person = User::factory()->create();

        $this->actingAs($person)->post(route('join.accept', $token))->assertRedirect();

        $this->assertTrue($team->fresh()->hasUser($person));
        $this->assertSame('member', $person->teamRole($team->fresh())->key);
        $this->assertSame(1, $link->fresh()->uses);

        // doing it again changes nothing and does not use up the link
        $this->actingAs($person)->post(route('join.accept', $token));
        $this->assertSame(1, $link->fresh()->uses);
    }

    public function test_a_link_with_one_use_left_works_once(): void
    {
        [$owner, $team] = $this->company();
        [$link, $token] = TeamInviteLink::issue($team, $owner, 7, 1);
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('join.accept', $token));
        $this->actingAs($b)->post(route('join.accept', $token));

        $this->assertTrue($team->fresh()->hasUser($a));
        $this->assertFalse($team->fresh()->hasUser($b));
        $this->actingAs($b)->get(route('join.show', $token))->assertNotFound()->assertSee('as many people as it allowed');
    }

    public function test_expired_revoked_and_made_up_links_do_not_work(): void
    {
        [$owner, $team] = $this->company();
        [$expired, $t1] = TeamInviteLink::issue($team, $owner);
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();
        [$revoked, $t2] = TeamInviteLink::issue($team, $owner);
        $revoked->forceFill(['revoked_at' => now()])->save();
        $person = User::factory()->create();

        foreach ([$t1, $t2, str_repeat('a', 48), 'short'] as $token) {
            $this->actingAs($person)->post(route('join.accept', $token));
            $this->assertFalse($team->fresh()->hasUser($person), $token);
        }
        $this->get(route('join.show', $t1))->assertNotFound()->assertSee('run out');
        $this->get(route('join.show', $t2))->assertNotFound()->assertSee('turned off');
    }

    public function test_a_full_company_refuses_a_person_with_a_link_and_the_use_is_not_counted(): void
    {
        [$owner, $team] = $this->company(paidSeats: 0);
        [$link, $token] = TeamInviteLink::issue($team, $owner);
        $person = User::factory()->create();

        $this->actingAs($person)->post(route('join.accept', $token))->assertSessionHasErrors();

        $this->assertFalse($team->fresh()->hasUser($person));
        $this->assertSame(0, $link->fresh()->uses);
    }

    public function test_an_owner_can_turn_a_link_off_and_it_stops_working(): void
    {
        [$owner, $team] = $this->company();
        [$link, $token] = TeamInviteLink::issue($team, $owner);
        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $team])->call('revokeInviteLink', $link->id);

        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'link_revoked']);
        $person = User::factory()->create();
        $this->actingAs($person)->post(route('join.accept', $token));
        $this->assertFalse($team->fresh()->hasUser($person));
    }

    public function test_another_companys_link_cannot_be_turned_off_from_here(): void
    {
        [$owner, $team] = $this->company();
        [$ownerB, $teamB] = $this->company();
        [$linkB] = TeamInviteLink::issue($teamB, $ownerB);
        $this->actingAs($owner);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(TeamMemberManager::class, ['team' => $team])->call('revokeInviteLink', $linkB->id);
    }
}
