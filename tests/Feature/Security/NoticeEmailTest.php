<?php

namespace Tests\Feature\Security;

use App\Jobs\SendNoticeEmail;
use App\Mail\AccountClosed;
use App\Mail\MembershipChanged;
use App\Mail\NoticeMail;
use App\Mail\SecurityNotice;
use App\Mail\WelcomeMail;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Notifications\AccountMail;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Ops\OpsCheck;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R-72 phase 2: notices that matter are emailed as they happen, the account emails that were missing now exist, and the framework's
 * own messages speak in InfoDot's words.
 */
class NoticeEmailTest extends TestCase
{
    use RefreshDatabase;

    private function person(array $over = []): User
    {
        return User::factory()->create($over + ['email_verified_at' => now(), 'name' => 'Thandi Nkosi']);
    }

    /** @return array{0: Team, 1: User} */
    private function company(): array
    {
        $owner = $this->person();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Lowveld Drilling']);

        return [$team, $owner];
    }

    private function standingNotice(User $user, Team $team): UserNotice
    {
        return UserNotice::create([
            'user_id' => $user->id, 'kind' => UserNotice::BUSINESS_STANDING, 'subject_type' => 'business',
            'subject_id' => $team->id, 'ref' => 'bs:review:1:lifted', 'created_at' => now(),
        ]);
    }

    public function test_only_security_and_business_notices_without_their_own_mail_are_covered(): void
    {
        $this->assertTrue(SendNoticeEmail::covers(UserNotice::IDENTITY_ALERT));
        $this->assertTrue(SendNoticeEmail::covers(UserNotice::BUSINESS_STANDING));
        $this->assertTrue(SendNoticeEmail::covers(UserNotice::VERIFICATION_QUEUE));
        $this->assertFalse(SendNoticeEmail::covers(UserNotice::ENQUIRY_RECEIVED), 'enquiries have their own email');
        $this->assertFalse(SendNoticeEmail::covers(UserNotice::TEAM_INVITATION));
        $this->assertFalse(SendNoticeEmail::covers(UserNotice::BOOKING_CONFIRMED));
    }

    public function test_recording_a_business_notice_queues_its_email(): void
    {
        Queue::fake();
        [$team, $owner] = $this->company();

        app(InAppNotices::class)->record(UserNotice::BUSINESS_STANDING, $owner, 'business', $team->id, 'bs:review:1:lifted');

        Queue::assertPushed(SendNoticeEmail::class, 1);
    }

    public function test_a_business_notice_is_emailed_in_the_words_the_bell_uses(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();

        (new SendNoticeEmail($this->standingNotice($owner, $team)->id))->handle(app(InAppNotices::class), app(NotificationPreferences::class));

        Mail::assertSent(NoticeMail::class, function (NoticeMail $m) use ($owner) {
            $html = $m->render();

            return $m->hasTo($owner->email) && str_contains($html, '“Lowveld Drilling” is back in the directory.')
                && str_contains($html, route('notices.preferences'));
        });
    }

    public function test_business_email_follows_the_persons_settings(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        app(NotificationPreferences::class)->save($owner, ['business' => ['popup' => true, 'email' => NotificationPreferences::OFF]]);

        (new SendNoticeEmail($this->standingNotice($owner, $team)->id))->handle(app(InAppNotices::class), app(NotificationPreferences::class));

        Mail::assertNothingSent();
    }

    public function test_an_unconfirmed_address_or_a_notice_the_person_may_no_longer_read_sends_nothing(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $former = $this->person();
        $team->users()->attach($former, ['role' => 'editor']);
        $notice = $this->standingNotice($former, $team);
        $team->users()->detach($former);

        (new SendNoticeEmail($notice->id))->handle(app(InAppNotices::class), app(NotificationPreferences::class));

        $owner->forceFill(['email_verified_at' => null])->save();
        (new SendNoticeEmail($this->standingNotice($owner, $team)->id))->handle(app(InAppNotices::class), app(NotificationPreferences::class));
        (new SendNoticeEmail(999999))->handle(app(InAppNotices::class), app(NotificationPreferences::class));

        Mail::assertNothingSent();
    }

    public function test_a_security_notice_is_sent_even_when_business_email_is_off(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        app(NotificationPreferences::class)->save($owner, ['business' => ['popup' => true, 'email' => NotificationPreferences::OFF]]);
        $notice = UserNotice::create([
            'user_id' => $owner->id, 'kind' => UserNotice::IDENTITY_ALERT, 'subject_type' => 'business',
            'subject_id' => $team->id, 'ref' => 'ia:1', 'created_at' => now(),
        ]);

        (new SendNoticeEmail($notice->id))->handle(app(InAppNotices::class), app(NotificationPreferences::class));

        Mail::assertSent(NoticeMail::class, fn (NoticeMail $m) => str_contains($m->render(), 'cannot be switched off'));
    }

    public function test_a_new_device_is_reported_but_the_first_and_a_known_one_are_not(): void
    {
        Mail::fake();
        $secret = 'sign-in-'.Str::random(12).'-7!';
        $user = $this->person(['password' => Hash::make($secret)]);
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
        $safari = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

        $signIn = function (string $agent) use ($user, $secret) {
            $this->app['auth']->forgetGuards();
            $this->withHeader('User-Agent', $agent)->post('/login', ['email' => $user->email, 'password' => $secret]);
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        };

        $signIn($chrome);
        $signIn($chrome);
        Mail::assertNotQueued(SecurityNotice::class);

        $signIn($safari);
        Mail::assertQueued(SecurityNotice::class, fn (SecurityNotice $m) => $m->kind === SecurityNotice::NEW_SIGN_IN && $m->hasTo($user->email)
            && str_contains($m->render(), 'Safari'));
        $this->assertSame(2, DB::table('user_devices')->where('user_id', $user->id)->count());
    }

    public function test_the_account_emails_that_were_missing_are_sent(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $member = $this->person(['name' => 'Sipho Mahlangu']);
        $mail = app(AccountMail::class);

        $mail->roleChanged($member, $team, 'Editor', $owner);
        $mail->companyDeleted([$owner, $member], 'Lowveld Drilling', $owner);
        $mail->suspension($member, true, 'Spam');
        $mail->accountClosed($member->email, $member->name);
        event(new Verified($member));

        Mail::assertQueued(MembershipChanged::class, fn ($m) => $m->kind === MembershipChanged::ROLE_CHANGED && $m->hasTo($member->email));
        Mail::assertQueued(MembershipChanged::class, fn ($m) => $m->kind === MembershipChanged::COMPANY_DELETED && $m->hasTo($member->email));
        Mail::assertNotQueued(MembershipChanged::class, fn ($m) => $m->kind === MembershipChanged::COMPANY_DELETED && $m->hasTo($owner->email));
        Mail::assertQueued(SecurityNotice::class, fn ($m) => $m->kind === SecurityNotice::SUSPENDED);
        Mail::assertQueued(AccountClosed::class, fn ($m) => $m->hasTo($member->email) && str_contains($m->render(), 'South African time'));
        Mail::assertQueued(WelcomeMail::class, fn ($m) => $m->hasTo($member->email));
    }

    public function test_the_framework_emails_use_infodots_words(): void
    {
        $user = $this->person();

        $reset = (new ResetPassword('token-abc'))->toMail($user);
        $this->assertSame('Choose a new InfoDot password', $reset->subject);
        $this->assertStringContainsString('token-abc', $reset->actionUrl);
        $this->assertSame('InfoDot', $reset->salutation);

        $verify = (new VerifyEmail)->toMail($user);
        $this->assertSame('Confirm your email address for InfoDot', $verify->subject);
        $this->assertSame('InfoDot', $verify->salutation);
    }

    public function test_company_knowledge_is_visible_only_to_its_author_and_the_companys_people(): void
    {
        [$team, $owner] = $this->company();
        $outsider = $this->person();
        $author = $this->person();
        $team->users()->attach($author, ['role' => 'editor']);
        $private = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $author->id, 'team_id' => $team->id, 'visibility' => 'company', 'question' => 'Our supplier terms?', 'description' => 'x']));
        $public = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $author->id, 'team_id' => $team->id, 'visibility' => 'public', 'question' => 'Supplier terms?', 'description' => 'x']));

        $this->assertTrue($private->visibleTo($author));
        $this->assertTrue($private->visibleTo($owner));
        $this->assertFalse($private->visibleTo($outsider));
        $this->assertFalse($private->visibleTo(null));
        $this->assertTrue($public->visibleTo(null));
    }

    public function test_the_ops_check_counts_failed_and_stuck_jobs(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHours(2)->getTimestamp(), 'created_at' => now()->subHours(2)->getTimestamp()]);

        $found = app(OpsCheck::class)->run();

        $this->assertSame(1, $found['queue_jobs_failed_24h']);
        $this->assertSame(1, $found['queue_jobs_waiting_1h']);
    }
}
