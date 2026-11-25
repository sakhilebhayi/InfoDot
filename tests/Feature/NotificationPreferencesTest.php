<?php

namespace Tests\Feature;

use App\Mail\NoticeDigest;
use App\Models\NotificationPreference;
use App\Models\Questions;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Notifications\NoticeDigests;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** R-64 phase 8: what a person chose to hear and how, the daily summary, and stopping it from the email. */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function person(array $over = []): User
    {
        return User::factory()->create($over + ['email_verified_at' => now(), 'name' => 'Zodwa Dlamini']);
    }

    /** A notice about a real, visible question (unless the test says what it is about), so it is not "no longer available". */
    private function notice(User $u, string $kind, array $over = []): UserNotice
    {
        static $n = 0;
        $n++;

        if (! isset($over['subject_type'])) {
            $q = Questions::create(['user_id' => $u->id, 'team_id' => null, 'visibility' => 'public', 'question' => 'How do I register a business number '.$n.'?', 'description' => 'I need the steps in order.']);
            $over += ['subject_type' => 'question', 'subject_id' => $q->id];
        }

        return UserNotice::create($over + ['user_id' => $u->id, 'kind' => $kind, 'subject_id' => 700000 + $n, 'ref' => 'p'.$n, 'created_at' => now()]);
    }

    private function prefs(): NotificationPreferences
    {
        return app(NotificationPreferences::class);
    }

    public function test_every_category_has_a_default_and_security_cannot_be_changed(): void
    {
        $p = $this->prefs()->for($this->person());

        $this->assertSame(array_keys(UserNotice::CATEGORIES), array_keys($p));
        $this->assertSame(['popup' => true, 'email' => 'instant', 'locked' => true], $p['security']);
        $this->assertSame('instant', $p['bookings']['email']);
        $this->assertSame('instant', $p['business']['email']);
        $this->assertSame('digest', $p['knowledge']['email']);
        $this->assertTrue($p['invitations']['popup']);
    }

    public function test_saving_takes_only_choices_a_category_offers(): void
    {
        $me = $this->person();

        $this->prefs()->save($me, [
            'bookings' => ['popup' => '0', 'email' => 'digest'],
            'knowledge' => ['popup' => '1', 'email' => 'instant'], // knowledge has no "as it happens": falls back to its default
            'security' => ['popup' => '0', 'email' => 'off'],        // locked: ignored
            'invitations' => ['popup' => '0'],                       // always pops up: ignored
            'seminars' => ['email' => 'off'],
        ]);

        $p = $this->prefs()->for($me);
        $this->assertSame(['popup' => false, 'email' => 'digest', 'locked' => false], $p['bookings']);
        $this->assertSame('digest', $p['knowledge']['email']);
        $this->assertSame('off', $p['seminars']['email']);
        $this->assertTrue($p['security']['popup']);
        $this->assertSame('instant', $p['security']['email']);
        $this->assertTrue($p['invitations']['popup']);
        $this->assertSame(0, NotificationPreference::where('user_id', $me->id)->whereIn('category', ['security', 'invitations'])->count());
    }

    public function test_the_settings_page_shows_each_category_with_security_locked_and_saves(): void
    {
        $me = $this->person();

        $this->actingAs($me)->get('/notifications/settings')->assertOk()
            ->assertSee('Notification settings')->assertSee('Account and security')->assertSee('This cannot be switched off')->assertSee('One daily summary')->assertSee('Email me as it happens');

        $this->actingAs($me)->post('/notifications/settings', [
            'prefs' => ['bookings' => ['popup' => '1', 'email' => 'off'], 'seminars' => ['popup' => '0', 'email' => 'digest']],
            'knowledge_in_app' => '0',
        ])->assertRedirect('/notifications/settings');

        $p = $this->prefs()->for($me->fresh());
        $this->assertSame('off', $p['bookings']['email']);
        $this->assertFalse($p['seminars']['popup']);
        $this->assertFalse($me->fresh()->wantsInAppNotices(), 'the older contribution switch lives on this page now');
    }

    public function test_the_settings_page_is_for_signed_in_people_and_rejects_junk(): void
    {
        $this->get('/notifications/settings')->assertRedirect('/login');
        $me = $this->person();
        $this->actingAs($me)->post('/notifications/settings', ['prefs' => ['bookings' => ['email' => str_repeat('x', 40)]]])->assertSessionHasErrors('prefs.bookings.email');
    }

    public function test_a_muted_category_does_not_pop_up_but_stays_on_the_list(): void
    {
        $me = $this->person();
        $first = $this->notice($me, UserNotice::ANSWER_RECEIVED);
        NotificationPreference::create(['user_id' => $me->id, 'category' => 'knowledge', 'popup' => false, 'email' => 'digest']);
        $muted = $this->notice($me, UserNotice::COMMENT_RECEIVED);
        $loud = $this->notice($me, UserNotice::BOOKING_RECEIVED, ['subject_type' => 'booking']);

        $json = $this->actingAs($me)->getJson('/notifications/arrivals?after='.$first->id)->assertOk()->json();

        $this->assertSame([$loud->id], collect($json['notices'])->pluck('id')->all());
        $this->assertNotNull(UserNotice::find($muted->id), 'still recorded');
        $this->actingAs($me)->get('/notifications')->assertOk()->assertSee('data-notice="comment_received"', false);
    }

    public function test_instant_email_follows_the_category_choice(): void
    {
        $me = $this->person();

        $this->assertTrue($this->prefs()->instantEmail($me, UserNotice::BOOKING_CONFIRMED));
        $this->assertFalse($this->prefs()->instantEmail($me, UserNotice::ANSWER_RECEIVED));

        NotificationPreference::create(['user_id' => $me->id, 'category' => 'bookings', 'email' => 'off']);
        $this->assertFalse($this->prefs()->instantEmail($me, UserNotice::BOOKING_CONFIRMED));
    }

    public function test_the_summary_lists_unread_new_notices_in_chosen_categories_once(): void
    {
        Mail::fake();
        $me = $this->person();
        $this->notice($me, UserNotice::ANSWER_RECEIVED);
        $this->notice($me, UserNotice::COMMENT_RECEIVED);
        $this->notice($me, UserNotice::BOOKING_RECEIVED, ['subject_type' => 'booking']);                         // bookings: instant by default, not in the summary
        $this->notice($me, UserNotice::MENTIONED, ['read_at' => now()]);                                         // read
        $this->notice($me, UserNotice::SOLUTION_VERIFIED, ['archived_at' => now()]);                             // put away
        $this->notice($me, UserNotice::KNOWLEDGE_REUSED, ['created_at' => now()->subDays(10)]);                  // too old

        $this->assertSame(1, app(NoticeDigests::class)->run());

        Mail::assertQueued(NoticeDigest::class, function (NoticeDigest $m) use ($me) {
            return $m->hasTo($me->email) && $m->total === 2 && count($m->sections) === 1 && $m->sections[0]['name'] === 'Questions and solutions'
                && str_contains($m->envelope()->subject, '2 new notifications');
        });

        // The same day again: nothing, and the last send is remembered.
        Mail::fake();
        $this->assertSame(0, app(NoticeDigests::class)->run());
        Mail::assertNothingQueued();
        $this->assertNotNull($me->fresh()->notices_digest_sent_at);
    }

    public function test_the_summary_leaves_out_reminders_and_things_the_person_can_no_longer_see_and_remembers_the_newest_it_covered(): void
    {
        Mail::fake();
        $me = $this->person();
        NotificationPreference::create(['user_id' => $me->id, 'category' => 'seminars', 'email' => 'digest']);
        $kept = UserNotice::create(['user_id' => $me->id, 'kind' => UserNotice::ANSWER_RECEIVED, 'subject_type' => 'question', 'subject_id' => 987654321, 'ref' => 'gone', 'created_at' => now()->subHours(5)]); // its question does not exist: shown as gone, so left out
        $this->notice($me, UserNotice::SEMINAR_REMINDER, ['subject_type' => 'seminar']);                    // a reminder: never in a summary
        $this->notice($me, UserNotice::BOOKING_REMINDER, ['subject_type' => 'booking']);

        $this->assertSame(0, app(NoticeDigests::class)->run(), 'only gone items and reminders: nothing worth a summary');
        Mail::assertNothingQueued();

        // A real, visible one makes a summary of one, and the cursor is the newest notice covered.
        $visible = $this->notice($me, UserNotice::ANSWER_RECEIVED, ['created_at' => now()->subMinutes(30)]);

        $this->assertSame(1, app(NoticeDigests::class)->run());
        Mail::assertQueued(NoticeDigest::class, fn (NoticeDigest $m) => $m->total === 1);
        $this->assertEquals($visible->created_at->timestamp, $me->fresh()->notices_digest_sent_at->timestamp);
        $this->assertNotNull($kept);
    }

    public function test_nothing_new_since_the_last_summary_means_no_summary(): void
    {
        Mail::fake();
        $me = $this->person(['notices_digest_sent_at' => now()->subHour()]);
        $this->notice($me, UserNotice::ANSWER_RECEIVED, ['created_at' => now()->subHours(3)]);

        $this->assertSame(0, app(NoticeDigests::class)->run());
        Mail::assertNothingQueued();
    }

    public function test_no_summary_for_an_unconfirmed_address_a_person_who_said_no_or_when_it_is_switched_off(): void
    {
        Mail::fake();
        $unconfirmed = $this->person(['email_verified_at' => null]);
        $declined = $this->person();
        NotificationPreference::create(['user_id' => $declined->id, 'category' => 'knowledge', 'email' => 'off']);
        $this->notice($unconfirmed, UserNotice::ANSWER_RECEIVED);
        $this->notice($declined, UserNotice::ANSWER_RECEIVED);

        $this->assertSame(0, app(NoticeDigests::class)->run());

        $willing = $this->person();
        $this->notice($willing, UserNotice::ANSWER_RECEIVED);
        config(['notifications.digest.enabled' => false]);
        Cache::flush();
        $this->assertSame(0, app(NoticeDigests::class)->run(), 'the kill switch stops every summary');
        Mail::assertNothingQueued();
    }

    public function test_the_summary_caps_each_category_and_carries_one_click_unsubscribe_and_no_inject(): void
    {
        $me = $this->person(['name' => '[Verify](https://evil.example) **x**']);
        foreach (range(1, 8) as $i) {
            $this->notice($me, UserNotice::ANSWER_RECEIVED);
        }

        $mail = null;
        Mail::fake();
        app(NoticeDigests::class)->run();
        Mail::assertQueued(NoticeDigest::class, function (NoticeDigest $m) use (&$mail) {
            $mail = $m;

            return true;
        });

        $html = $mail->render();
        $this->assertCount(5, $mail->sections[0]['items']);
        $this->assertStringContainsString('and 3 more', $html);
        $this->assertStringContainsString('stop these summaries', $html);
        $this->assertStringContainsString(route('notices.preferences'), $html);
        $this->assertStringNotContainsString('href="https://evil.example"', $html);

        $headers = $mail->headers()->text;
        $this->assertStringStartsWith('<'.url('/notifications/unsubscribe/'.$me->id), $headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
    }

    public function test_unsubscribing_from_the_email_needs_the_signed_link_and_turns_every_summary_off(): void
    {
        $me = $this->person();
        $other = $this->person();
        $url = URL::signedRoute('notices.unsubscribe', ['user' => $me->id]);

        $this->get('/notifications/unsubscribe/'.$me->id)->assertForbidden();           // unsigned
        $this->post('/notifications/unsubscribe/'.$me->id)->assertForbidden();
        $this->get(str_replace((string) $me->id, (string) $other->id, $url))->assertForbidden(); // signature belongs to someone else

        $this->get($url)->assertOk()->assertSee('Stop the daily summary');
        $this->assertSame(['knowledge', 'seminars', 'platforms'], array_values(array_intersect(['knowledge', 'seminars', 'platforms'], $this->prefs()->digestCategories($me))));

        $this->post($url)->assertOk()->assertSee('You will not get the daily summary'); // no CSRF token: a mail client's one-click POST
        $this->assertSame([], $this->prefs()->digestCategories($me->fresh()));
        $this->assertSame('instant', $this->prefs()->for($me->fresh())['bookings']['email'], 'as-it-happens email is left alone');
        $this->assertNotSame([], $this->prefs()->digestCategories($other), 'nobody else was changed');
    }

    public function test_the_mail_footer_points_to_the_settings_page_and_the_schedule_has_the_digest(): void
    {
        $html = (string) file_get_contents(resource_path('views/vendor/mail/html/message.blade.php'));
        $this->assertStringContainsString("route('notices.preferences')", $html);

        $events = collect(app(Schedule::class)->events());
        $this->assertTrue($events->contains(fn ($e) => str_contains((string) $e->command, 'notices:digest')));
    }
}
