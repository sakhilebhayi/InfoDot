<?php

namespace Tests\Feature\Stabilisation;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Notifications\EmailAddressChanged;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Changing the email on an account moves password resets and sign-in codes to the new address. The
 * old address is told, so a change the owner did not make is noticed while it can still be undone.
 */
class EmailChangeNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_address_is_told_when_the_email_changes(): void
    {
        Notification::fake();
        $user = User::factory()->create(['name' => 'Pat Example', 'email' => 'old@example.com']);

        app(UpdateUserProfileInformation::class)->update($user, ['name' => 'Pat Example', 'email' => 'new@example.com']);

        $this->assertSame('new@example.com', $user->fresh()->email);
        Notification::assertSentOnDemand(
            EmailAddressChanged::class,
            fn (EmailAddressChanged $notice, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'old@example.com' && $channels === ['mail'],
        );
        Notification::assertSentOnDemandTimes(EmailAddressChanged::class, 1);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_notice_says_what_happened_without_naming_the_new_address(): void
    {
        $mail = (new EmailAddressChanged('Pat Example'))->toMail(new AnonymousNotifiable);
        $text = implode(' ', [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines]);

        $this->assertStringContainsString('email address', $text);
        $this->assertStringContainsString('Pat Example', $text);
        $this->assertSame(route('contact'), $mail->actionUrl);
        $this->assertStringNotContainsString('new@example.com', $text);
    }

    public function test_nothing_is_sent_when_the_email_stays_the_same(): void
    {
        Notification::fake();
        $user = User::factory()->create(['name' => 'Pat Example', 'email' => 'old@example.com']);

        app(UpdateUserProfileInformation::class)->update($user, ['name' => 'Pat Renamed', 'email' => 'old@example.com']);

        $this->assertSame('Pat Renamed', $user->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_the_change_is_saved_when_the_mail_server_cannot_be_reached(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        Log::spy();
        $user = User::factory()->create(['name' => 'Pat Example', 'email' => 'old@example.com']);

        app(UpdateUserProfileInformation::class)->update($user, ['name' => 'Pat Example', 'email' => 'new@example.com']);

        $this->assertSame('new@example.com', $user->fresh()->email);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'profile.email_change_notice_failed')->once();
        // The verification email to the new address fails the same way and is handled the same way.
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'profile.verification_mail_failed')->once();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
