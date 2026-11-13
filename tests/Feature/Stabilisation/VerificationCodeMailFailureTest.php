<?php

namespace Tests\Feature\Stabilisation;

use App\Models\BusinessVerification;
use App\Models\Team;
use App\Models\User;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * R-49. The one-time code that proves a business controls its email domain was saved before it was emailed. When
 * the mail server refused it the page answered 500, and asking again was refused for a minute because "a code was
 * sent". A failed send now saves no code, says so on the form, and can be retried at once.
 */
class VerificationCodeMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_code_that_cannot_be_emailed_is_not_kept_and_can_be_asked_for_again(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $verification = BusinessVerification::forceCreate([
            'team_id' => $team->id, 'applicant_id' => $owner->id, 'status' => BusinessVerification::PENDING,
            'category' => 'registered_company', 'registered_name' => 'Acme Boreholes (Pty) Ltd', 'registration_country' => 'South Africa',
            'website' => 'https://www.acmeboreholes.co.za', 'applicant_role' => 'Director', 'declared_at' => now(),
        ]);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        Log::spy();

        try {
            app(VerificationService::class)->sendEmailCode($owner, $verification, 'accounts@acmeboreholes.co.za');
            $this->fail('The send was reported as done.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('official_email', $e->errors());
            $this->assertStringContainsString('could not be sent', $e->errors()['official_email'][0]);
        }

        $fresh = $verification->fresh();
        $this->assertNull($fresh->email_code_hash);
        $this->assertNull($fresh->email_code_expires_at);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'verification.email_code_mail_failed')->once();

        // The mail server is back: the same request goes through straight away.
        Mail::fake();
        app(VerificationService::class)->sendEmailCode($owner, $fresh, 'accounts@acmeboreholes.co.za');
        $this->assertNotNull($verification->fresh()->email_code_hash);
    }
}
