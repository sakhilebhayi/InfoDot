<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * R-49. The host sends mail inside the request. With the mail server down, asking for a password reset link or for
 * the verification email again answered 500. Both now return to their form and say the email did not go; a reset
 * token that was never delivered is not kept, so the person can ask again without waiting.
 */
class AccountMailFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        Log::spy();
    }

    public function test_a_reset_link_that_cannot_be_emailed_returns_to_the_form_with_a_message(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasErrors('email');
        $this->assertSame(0, DB::table(config('auth.passwords.users.table'))->where('email', $user->email)->count());
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'account.password_reset_mail_failed')->once();
    }

    public function test_a_verification_email_that_cannot_be_sent_again_returns_to_the_page_with_a_message(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->from('/email/verify')->post('/email/verification-notification');

        $response->assertRedirect('/email/verify');
        $response->assertSessionHas('verification_mail_failed', true);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'account.verification_mail_failed')->once();
    }
}
