<?php

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Mail\MembershipChanged;
use App\Mail\SecurityNotice;
use App\Models\Team;
use App\Models\User;
use App\Services\Notifications\AccountMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Events\TeamMemberRemoved;
use Tests\TestCase;

/** R-64 phase 7: the emails about a person's own account and company membership, and the shared mail theme they use. */
class AccountMailTest extends TestCase
{
    use RefreshDatabase;

    /** A throwaway secret made fresh for each use, so no password-like text sits in the repository. */
    private function secret(string $tag): string
    {
        return $tag.'-'.Str::random(10).'-'.random_int(100, 999).'!';
    }

    private string $oldSecret = '';

    private function person(array $over = []): User
    {
        $this->oldSecret = $this->oldSecret ?: $this->secret('old');

        return User::factory()->create($over + ['email_verified_at' => now(), 'name' => 'Zodwa Dlamini', 'password' => Hash::make($this->oldSecret)]);
    }

    private function company(): array
    {
        $owner = $this->person(['name' => 'Owner Person']);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Northwind Finance']);

        return [$team, $owner];
    }

    public function test_changing_the_password_tells_the_account_once_and_never_carries_a_secret(): void
    {
        Mail::fake();
        $user = $this->person();

        $new = $this->secret('new');
        app(UpdateUserPassword::class)->update($user, ['current_password' => $this->oldSecret, 'password' => $new, 'password_confirmation' => $new]);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(SecurityNotice::class, function (SecurityNotice $m) use ($user, $new) {
            $html = $m->render();

            return $m->hasTo($user->email) && $m->kind === SecurityNotice::PASSWORD_CHANGED
                && str_contains($m->envelope()->subject, 'password was changed')
                && str_contains($html, 'Reset your password') && str_contains($html, route('password.request'))
                && ! str_contains($html, $new) && ! str_contains($html, $this->oldSecret);
        });
    }

    public function test_a_password_reset_also_tells_the_account(): void
    {
        Mail::fake();
        $user = $this->person();

        $new = $this->secret('new');
        app(ResetUserPassword::class)->reset($user, ['password' => $new, 'password_confirmation' => $new]);

        Mail::assertQueued(SecurityNotice::class, fn (SecurityNotice $m) => $m->kind === SecurityNotice::PASSWORD_CHANGED && $m->hasTo($user->email));
    }

    public function test_turning_two_factor_on_or_off_tells_the_account(): void
    {
        Mail::fake();
        $user = $this->person();

        TwoFactorAuthenticationEnabled::dispatch($user);
        TwoFactorAuthenticationDisabled::dispatch($user);

        Mail::assertQueued(SecurityNotice::class, fn (SecurityNotice $m) => $m->kind === SecurityNotice::TWO_FACTOR_ON && str_contains($m->envelope()->subject, 'turned on'));
        Mail::assertQueued(SecurityNotice::class, fn (SecurityNotice $m) => $m->kind === SecurityNotice::TWO_FACTOR_OFF && str_contains($m->envelope()->subject, 'turned off'));
    }

    public function test_a_security_message_goes_to_an_unconfirmed_address_too(): void
    {
        Mail::fake();
        $user = $this->person(['email_verified_at' => null]);

        app(AccountMail::class)->passwordChanged($user);

        Mail::assertQueued(SecurityNotice::class, fn (SecurityNotice $m) => $m->hasTo($user->email));
    }

    public function test_a_failed_send_never_breaks_what_the_person_was_doing(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $user = $this->person();

        app(AccountMail::class)->passwordChanged($user);
        $this->assertTrue(true, 'no exception reached the caller');
    }

    public function test_being_added_by_someone_else_is_mailed_but_joining_by_your_own_hand_is_not(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $member = $this->person(['name' => 'Thandi Nkosi']);

        $this->actingAs($owner);
        TeamMemberAdded::dispatch($team, $member);
        Mail::assertQueued(MembershipChanged::class, fn (MembershipChanged $m) => $m->hasTo($member->email) && $m->kind === MembershipChanged::ADDED && str_contains($m->envelope()->subject, 'You were added to Northwind Finance') && str_contains($m->render(), route('dashboard')));

        Mail::fake();
        $this->actingAs($member);
        TeamMemberAdded::dispatch($team, $member);
        Mail::assertNothingQueued();
    }

    public function test_being_removed_by_someone_else_is_mailed_but_leaving_is_not(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $member = $this->person();

        $this->actingAs($owner);
        TeamMemberRemoved::dispatch($team, $member);
        Mail::assertQueued(MembershipChanged::class, fn (MembershipChanged $m) => $m->kind === MembershipChanged::REMOVED && str_contains($m->envelope()->subject, 'removed from Northwind Finance'));

        Mail::fake();
        $this->actingAs($member);
        TeamMemberRemoved::dispatch($team, $member);
        Mail::assertNothingQueued();
    }

    public function test_nobody_unconfirmed_and_no_personal_workspace_is_mailed_about_membership(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $unconfirmed = $this->person(['email_verified_at' => null]);
        $personal = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => true]);
        $member = $this->person();

        $this->actingAs($owner);
        TeamMemberAdded::dispatch($team, $unconfirmed);
        TeamMemberAdded::dispatch($personal, $member);

        Mail::assertNothingQueued();
    }

    public function test_a_company_name_cannot_inject_markup_into_the_message(): void
    {
        $team = Team::factory()->create(['personal_team' => false, 'name' => "Evil **Co**\n<script>alert(1)</script> [click](https://evil.example)"]);
        $member = $this->person();
        $plain = MembershipChanged::plainName($team);

        $this->assertStringNotContainsString("\n", $plain);
        $this->assertStringNotContainsString('<script', $plain);

        $html = (new MembershipChanged(MembershipChanged::ADDED, $member, $plain))->render();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringNotContainsString('<strong>Co</strong>', $html);
    }

    public function test_the_mail_theme_has_dark_mode_a_fallback_link_and_a_way_to_manage_notifications(): void
    {
        $html = (new SecurityNotice(SecurityNotice::PASSWORD_CHANGED, $this->person(), '25 November 2026, 10:00'))->render();

        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString('color-scheme" content="light dark"', $html);
        $this->assertStringContainsString('If the button does not work, copy this link', $html);
        $this->assertStringContainsString(route('password.request'), $html);
        $this->assertStringContainsString('Manage your notifications', $html);
        $this->assertStringContainsString(route('notices'), $html);
        $this->assertStringContainsString('meta name="viewport"', $html, 'responsive');
        $this->assertStringContainsString('cannot be switched off', $html);
    }

    public function test_the_plain_text_version_carries_the_links_too(): void
    {
        $mail = new SecurityNotice(SecurityNotice::PASSWORD_CHANGED, $this->person(), 'now');
        $text = (string) app(Markdown::class)->renderText('emails.security-notice', $mail->content()->with);

        $this->assertStringContainsString(route('password.request'), $text);
        $this->assertStringContainsString(route('notices'), $text);
    }

    public function test_the_scheduler_runs_a_queue_worker_with_a_real_queue_or_while_work_is_left_behind(): void
    {
        $find = fn () => collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'queue:work'));

        config(['queue.default' => 'sync']);
        $this->assertFalse($find()->filtersPass($this->app), 'nothing to work through with the sync queue and an empty table');

        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $this->assertTrue($find()->filtersPass($this->app), 'rows left over after switching back to sync are still worked through');

        DB::table('jobs')->delete();
        config(['queue.default' => 'database']);
        $this->assertTrue($find()->filtersPass($this->app));
        $this->assertStringContainsString('--stop-when-empty', $find()->command);
        $this->assertStringContainsString('queue:work database', $find()->command);
    }

    public function test_repeating_an_action_cannot_fill_an_inbox(): void
    {
        Mail::fake();
        [$team, $owner] = $this->company();
        $user = $this->person();

        foreach (range(1, 4) as $i) {
            app(AccountMail::class)->passwordChanged($user);
        }
        Mail::assertQueuedCount(1);

        Mail::fake();
        $this->actingAs($owner);
        foreach (range(1, 3) as $i) {
            TeamMemberAdded::dispatch($team, $user);
            TeamMemberRemoved::dispatch($team, $user);
        }
        Mail::assertQueued(MembershipChanged::class, 2); // one "added" and one "removed" that day, not six
    }

    public function test_an_unconfirmed_address_is_greeted_without_the_name_someone_typed_and_names_cannot_make_links(): void
    {
        $stranger = $this->person(['email_verified_at' => null, 'name' => '[Verify your account](https://evil.example)']);
        $html = (new SecurityNotice(SecurityNotice::PASSWORD_CHANGED, $stranger, 'now'))->render();

        $this->assertStringContainsString('Hello,', $html);
        $this->assertStringNotContainsString('evil.example', $html);

        $confirmed = $this->person(['name' => '[Verify your account](https://evil.example) **bold**']);
        $mine = (new SecurityNotice(SecurityNotice::PASSWORD_CHANGED, $confirmed, 'now'))->render();
        $this->assertStringNotContainsString('href="https://evil.example"', $mine);
        $this->assertStringNotContainsString('<strong>bold</strong>', $mine);
    }

    public function test_a_message_for_an_account_that_is_gone_is_dropped_and_a_failed_one_is_logged(): void
    {
        $mail = new SecurityNotice(SecurityNotice::PASSWORD_CHANGED, $this->person(), 'now');

        $this->assertTrue($mail->deleteWhenMissingModels);
        $this->assertTrue((new MembershipChanged(MembershipChanged::ADDED, $this->person(), 'X'))->deleteWhenMissingModels);

        Log::spy();
        $mail->failed(new \RuntimeException('x'));
        Log::shouldHaveReceived('warning')->with('account.mail_failed', \Mockery::type('array'));
    }

    public function test_the_jobs_table_exists_for_a_database_queue_and_the_default_is_still_sync(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', (string) file_get_contents(base_path('.env.example')));
    }
}
