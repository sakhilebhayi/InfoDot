<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * InfoDot is the identity authority for the Dot ecosystem: other apps match people
 * by the email InfoDot vouches for, so InfoDot must have checked that the person
 * owns it. Verification was switched off, which left every account unverified.
 */
class EmailVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Amara Dlamini',
            'email' => 'amara@example.test',
            'password' => 'a-long-enough-Passw0rd!',
            'password_confirmation' => 'a-long-enough-Passw0rd!',
            'terms' => true,
        ]);

        $user = User::where('email', 'amara@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_an_unverified_person_is_sent_to_the_verification_screen_not_the_app(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get('/questions')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/platforms')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_following_the_emailed_link_verifies_and_unlocks_the_app(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get('/questions')->assertOk();
    }

    public function test_a_wrong_link_does_not_verify(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.test'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_the_verification_screen_can_resend_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => null]);

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_an_unverified_account_still_cannot_be_vouched_for_to_other_platforms(): void
    {
        config([
            'ecosystem.service_secrets' => ['files' => 'files-secret-for-tests'],
            'ecosystem.platforms' => ['files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true]],
        ]);

        $user = User::factory()->create(['email_verified_at' => null]);
        Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $token = $user->createToken(EcosystemHandoff::tokenName('files'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer files-secret-for-tests'])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'files'])
            ->assertForbidden();
    }
}
