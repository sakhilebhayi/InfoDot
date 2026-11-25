<?php

namespace Tests\Feature;

use App\Actions\Jetstream\InviteTeamMember;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamLicence;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** R-64 phase 6: categories, archive, pop-ups, invitations answered from a notice, and what a person who left a company may still read. */
class NotificationCentreTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name = 'Zodwa Dlamini'): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'name' => $name]);
    }

    private function notice(User $user, string $kind = UserNotice::ANSWER_RECEIVED, array $over = []): UserNotice
    {
        static $n = 0;

        return UserNotice::create($over + ['user_id' => $user->id, 'kind' => $kind, 'subject_type' => 'question', 'subject_id' => 999000 + (++$n), 'ref' => 'r'.$n, 'created_at' => now()]);
    }

    /** A company whose owner can invite (one paid seat). */
    private function company(string $name = 'Northwind Finance'): array
    {
        $owner = $this->person('Owner Person');
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => $name]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => 2, 'payment_status' => 'active']);

        return [$team, $owner];
    }

    public function test_every_kind_has_a_category_that_exists(): void
    {
        foreach (UserNotice::KINDS as $kind) {
            $this->assertArrayHasKey(UserNotice::categoryOf($kind), UserNotice::CATEGORIES, $kind);
        }
        $this->assertSame('invitations', UserNotice::categoryOf(UserNotice::TEAM_INVITATION));
        $this->assertSame('bookings', UserNotice::categoryOf(UserNotice::BOOKING_CONFIRMED));
        $this->assertSame('security', UserNotice::categoryOf(UserNotice::IDENTITY_ALERT));
        $this->assertSame('seminars', UserNotice::categoryOf(UserNotice::SEMINAR_CANCELLED));
        $this->assertSame('knowledge', UserNotice::categoryOf(UserNotice::ANSWER_RECEIVED));
    }

    public function test_invitations_cannot_be_switched_off(): void
    {
        $this->assertContains(UserNotice::TEAM_INVITATION, UserNotice::SERVICE_KINDS);
    }

    public function test_the_page_filters_by_category_and_counts_each(): void
    {
        $me = $this->person();
        $this->notice($me, UserNotice::ANSWER_RECEIVED);
        $this->notice($me, UserNotice::BOOKING_RECEIVED, ['subject_type' => 'booking']);

        $all = $this->actingAs($me)->get('/notifications')->assertOk();
        $all->assertSee('Questions and solutions')->assertSee('Bookings and appointments');

        $bookings = $this->actingAs($me)->get('/notifications?category=bookings')->assertOk()->getContent();
        $this->assertStringContainsString('data-notice="booking_received"', $bookings);
        $this->assertStringNotContainsString('data-notice="answer_received"', $bookings);

        $this->actingAs($me)->get('/notifications?category=nonsense')->assertOk(); // an unknown category is just "all"
    }

    public function test_putting_a_notice_away_removes_it_from_the_list_and_the_badge_and_it_can_come_back(): void
    {
        $me = $this->person();
        $n = $this->notice($me);

        $this->actingAs($me)->post("/notifications/{$n->id}/archive")->assertRedirect();
        $n->refresh();
        $this->assertNotNull($n->archived_at);
        $this->assertNotNull($n->read_at, 'a notice put away no longer holds the badge up');
        $this->assertSame(0, app(InAppNotices::class)->unreadCount($me));

        $this->actingAs($me)->get('/notifications')->assertOk()->assertDontSee('data-notice="answer_received"', false);
        $this->actingAs($me)->get('/notifications?show=archived')->assertOk()->assertSee('data-notice="answer_received"', false)->assertSee('Bring back');

        $this->actingAs($me)->post("/notifications/{$n->id}/restore")->assertRedirect();
        $this->assertNull($n->fresh()->archived_at);
    }

    public function test_putting_read_ones_away_leaves_unread_ones(): void
    {
        $me = $this->person();
        $read = $this->notice($me, UserNotice::ANSWER_RECEIVED, ['read_at' => now()]);
        $unread = $this->notice($me, UserNotice::COMMENT_RECEIVED);

        $this->actingAs($me)->post('/notifications/archive-read')->assertRedirect();

        $this->assertNotNull($read->fresh()->archived_at);
        $this->assertNull($unread->fresh()->archived_at);
    }

    public function test_nobody_can_archive_restore_or_answer_somebody_elses_notice(): void
    {
        $owner = $this->person('Owner');
        $other = $this->person('Other');
        $n = $this->notice($owner);

        $this->actingAs($other)->post("/notifications/{$n->id}/archive")->assertNotFound();
        $this->actingAs($other)->post("/notifications/{$n->id}/restore")->assertNotFound();
        $this->actingAs($other)->post("/notifications/{$n->id}/accept")->assertNotFound();
        $this->assertNull($n->fresh()->archived_at);
    }

    public function test_arrivals_returns_only_my_newer_unread_notices_and_the_unread_count(): void
    {
        $me = $this->person();
        $other = $this->person('Other');
        $old = $this->notice($me);
        $new = $this->notice($me, UserNotice::COMMENT_RECEIVED);
        $this->notice($other, UserNotice::COMMENT_RECEIVED);
        $this->notice($me, UserNotice::ANSWER_ACCEPTED, ['read_at' => now()]);
        $this->notice($me, UserNotice::MENTIONED, ['created_at' => now()->subMinutes(30)]);

        $json = $this->actingAs($me)->getJson('/notifications/arrivals?after='.$old->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

        $this->assertSame([$new->id], collect($json['notices'])->pluck('id')->all(), 'newer, unread, mine and recent only');
        $this->assertSame(UserNotice::where('user_id', $me->id)->whereNull('read_at')->count(), $json['unread']);
        $this->assertGreaterThanOrEqual($new->id, $json['last']);
        $this->assertArrayHasKey('text', $json['notices'][0]);
        $this->assertStringContainsString('/notifications', $json['notices'][0]['href']);
    }

    public function test_arrivals_with_nothing_new_keeps_the_last_id_and_is_for_signed_in_people_only(): void
    {
        $me = $this->person();
        $n = $this->notice($me);

        $json = $this->actingAs($me)->getJson('/notifications/arrivals?after='.$n->id)->assertOk()->json();
        $this->assertSame([], $json['notices']);
        $this->assertSame($n->id, $json['last']);
    }

    public function test_arrivals_is_for_signed_in_people_only(): void
    {
        $this->getJson('/notifications/arrivals')->assertUnauthorized();
    }

    public function test_an_archived_or_hidden_notice_is_never_popped_up(): void
    {
        $me = $this->person();
        $n = $this->notice($me, UserNotice::COMMENT_RECEIVED, ['archived_at' => now(), 'read_at' => now()]);

        $json = $this->actingAs($me)->getJson('/notifications/arrivals?after='.($n->id - 1))->assertOk()->json();

        $this->assertSame([], $json['notices']);
    }

    public function test_signed_in_pages_carry_the_popup_area_with_the_newest_id_and_the_throttle_is_its_own(): void
    {
        $me = $this->person();
        $n = $this->notice($me);

        $html = $this->actingAs($me)->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString('class="ix-toasts"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('last: '.$n->id, $html);
        $this->assertStringContainsString('notifications\\/arrivals', $html);

        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'notices.arrivals');
        $this->assertContains('throttle:30,1,notices.arrivals', $route->gatherMiddleware());
    }

    public function test_inviting_someone_with_a_confirmed_account_puts_the_invitation_in_their_notifications(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $invitee = $this->person('Thandi Nkosi');

        app(InviteTeamMember::class)->invite($owner, $team, strtoupper($invitee->email), 'editor');

        $notice = UserNotice::where('user_id', $invitee->id)->sole();
        $this->assertSame(UserNotice::TEAM_INVITATION, $notice->kind);
        $this->assertSame('invitation', $notice->subject_type);

        $page = $this->actingAs($invitee)->get('/notifications')->assertOk();
        $page->assertSee('You have been invited to join', false)->assertSee('Northwind Finance')->assertSee('Accept')->assertSee('Decline');
    }

    public function test_nobody_is_told_in_the_app_when_the_address_has_no_confirmed_account(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        User::factory()->create(['email' => 'unconfirmed@example.test', 'email_verified_at' => null]);

        app(InviteTeamMember::class)->invite($owner, $team, 'unconfirmed@example.test', 'editor');
        app(InviteTeamMember::class)->invite($owner, $team, 'nobody-yet@example.test', 'editor');

        $this->assertSame(0, UserNotice::where('kind', UserNotice::TEAM_INVITATION)->count());
    }

    private function invited(): array
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $invitee = $this->person('Thandi Nkosi');
        app(InviteTeamMember::class)->invite($owner, $team, $invitee->email, 'editor');

        return [$team, $owner, $invitee, UserNotice::where('user_id', $invitee->id)->sole(), TeamInvitation::where('team_id', $team->id)->sole()];
    }

    public function test_accepting_from_the_notice_joins_the_company_and_closes_the_invitation(): void
    {
        [$team, , $invitee, $notice, $invitation] = $this->invited();

        $this->actingAs($invitee)->post("/notifications/{$notice->id}/accept")->assertRedirect(route('teams.show', $team));

        $this->assertTrue($invitee->fresh()->belongsToTeam($team));
        $this->assertNull(TeamInvitation::find($invitation->id));
        $this->assertNotNull($notice->fresh()->archived_at);
    }

    public function test_an_invitation_sent_to_the_address_in_other_letters_is_accepted_and_counts_its_seat_once(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $invitee = $this->person('Thandi Nkosi');
        app(InviteTeamMember::class)->invite($owner, $team, strtoupper($invitee->email), 'editor');
        $notice = UserNotice::where('user_id', $invitee->id)->sole();

        $this->actingAs($invitee)->post("/notifications/{$notice->id}/accept")->assertRedirect(route('teams.show', $team));

        $this->assertTrue($invitee->fresh()->belongsToTeam($team));
        $this->assertSame(0, TeamInvitation::where('team_id', $team->id)->count());
    }

    public function test_someone_already_in_the_company_closes_the_invitation_instead_of_keeping_it_open(): void
    {
        [$team, , $invitee, $notice, $invitation] = $this->invited();
        $team->users()->attach($invitee, ['role' => 'member']);

        $this->actingAs($invitee)->post("/notifications/{$notice->id}/accept")->assertRedirect(route('notices'));

        $this->assertNull(TeamInvitation::find($invitation->id), 'the invitation no longer holds a seat');
        $this->assertNotNull($notice->fresh()->archived_at);
        $this->assertSame(1, $team->users()->where('users.id', $invitee->id)->count(), 'no second membership row');
    }

    public function test_declining_from_the_notice_removes_the_invitation_and_joins_nothing(): void
    {
        [$team, , $invitee, $notice, $invitation] = $this->invited();

        $this->actingAs($invitee)->post("/notifications/{$notice->id}/decline")->assertRedirect(route('notices'));

        $this->assertFalse($invitee->fresh()->belongsToTeam($team));
        $this->assertNull(TeamInvitation::find($invitation->id));
        $this->assertNotNull($notice->fresh()->archived_at);
    }

    public function test_an_invitation_that_ran_out_or_was_withdrawn_cannot_be_accepted(): void
    {
        [$team, , $invitee, $notice, $invitation] = $this->invited();
        $invitation->forceFill(['expires_at' => Carbon::now()->subDay()])->save();

        $this->actingAs($invitee)->get('/notifications')->assertOk()->assertDontSee('>Accept<', false);
        $this->actingAs($invitee)->post("/notifications/{$notice->id}/accept")->assertRedirect(route('notices'));
        $this->assertFalse($invitee->fresh()->belongsToTeam($team));

        $invitation->delete();
        $again = $this->notice($invitee, UserNotice::TEAM_INVITATION, ['subject_type' => 'invitation', 'subject_id' => $invitation->id, 'ref' => 'inv:gone']);
        $this->actingAs($invitee)->post("/notifications/{$again->id}/accept")->assertRedirect(route('notices'));
        $this->assertFalse($invitee->fresh()->belongsToTeam($team));
    }

    public function test_a_notice_pointing_at_an_invitation_for_somebody_else_is_refused(): void
    {
        [$team, , , , $invitation] = $this->invited();
        $stranger = $this->person('Stranger');
        $forged = $this->notice($stranger, UserNotice::TEAM_INVITATION, ['subject_type' => 'invitation', 'subject_id' => $invitation->id, 'ref' => 'inv:forged']);

        $this->actingAs($stranger)->get('/notifications')->assertOk()->assertDontSee('>Accept<', false);
        $this->actingAs($stranger)->post("/notifications/{$forged->id}/accept")->assertRedirect(route('notices'));

        $this->assertFalse($stranger->fresh()->belongsToTeam($team));
        $this->assertNotNull(TeamInvitation::find($invitation->id), 'someone else answering must not use up the invitation');
    }

    public function test_the_answer_route_only_takes_accept_or_decline(): void
    {
        $me = $this->person();
        $n = $this->notice($me, UserNotice::TEAM_INVITATION, ['subject_type' => 'invitation']);

        $this->actingAs($me)->post("/notifications/{$n->id}/shrug")->assertNotFound();
    }

    public function test_a_person_who_left_a_company_no_longer_reads_its_standing_on_an_old_notice(): void
    {
        [$team, $owner] = $this->company('Northwind Finance');
        $member = $this->person('Member');
        $team->users()->attach($member, ['role' => 'member']);
        $notice = $this->notice($member, UserNotice::BUSINESS_STANDING, ['subject_type' => 'business', 'subject_id' => $team->id, 'ref' => 'bs:review:1:restricted']);

        $this->actingAs($member)->get('/notifications')->assertOk()->assertSee('Northwind Finance');

        $team->users()->detach($member);

        $page = $this->actingAs($member->fresh())->get('/notifications')->assertOk();
        $page->assertDontSee('Northwind Finance');
        $this->assertNotNull($notice->fresh());
    }

    public function test_a_report_decision_is_still_readable_by_the_person_who_reported(): void
    {
        [$team] = $this->company('Northwind Finance');
        $reporter = $this->person('Reporter');
        $this->notice($reporter, UserNotice::BUSINESS_REPORT_DECIDED, ['subject_type' => 'business', 'subject_id' => $team->id, 'ref' => 'br:1:actioned']);

        $this->actingAs($reporter)->get('/notifications')->assertOk()->assertSee('Your report about');
    }

    public function test_the_stylesheet_and_script_for_popups_exist_and_links_stay_on_this_site(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        $js = (string) file_get_contents(resource_path('js/notices.js'));

        foreach (['.ix-toasts', '.ix-toast__close', '.ix-notices__filters', '.ix-notices__acts'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertStringContainsString('url.origin === window.location.origin', $js, 'a pop-up link can only lead to this site');
        $this->assertStringContainsString('visibilitychange', $js);
        $this->assertStringContainsString('[401, 403, 419]', $js, 'polling stops when the session has ended');
        $this->assertStringNotContainsString('x-html', (string) file_get_contents(resource_path('views/components/ix/toasts.blade.php')));
    }
}
