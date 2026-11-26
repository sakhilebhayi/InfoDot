<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * R-72 (F-06): the second step of signing in, over HTTP. A password alone never signs in an account with two-factor on;
 * the code from the app does, a recovery code does once, and a wrong code does not.
 */
class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = '';

    private string $password = '';

    /** @return array{0: User, 1: string} the person and their one recovery code */
    private function personWithTwoFactor(): array
    {
        $this->password = 'challenge-'.Str::random(12).'-4!';
        $this->secret = app(Google2FA::class)->generateSecretKey();
        $recovery = Str::random(10).'-'.Str::random(10);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => Hash::make($this->password),
        ]);
        $user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_recovery_codes' => encrypt(json_encode([$recovery])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $recovery];
    }

    private function passwordStep(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => $this->password])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_the_password_alone_does_not_sign_in_and_the_code_from_the_app_does(): void
    {
        [$user] = $this->personWithTwoFactor();

        $this->passwordStep($user);
        $this->get('/two-factor-challenge')->assertOk();

        $this->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($this->secret)])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_is_refused(): void
    {
        [$user] = $this->personWithTwoFactor();

        $this->passwordStep($user);
        $current = app(Google2FA::class)->getCurrentOtp($this->secret);
        $wrong = str_pad((string) (((int) $current + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->post('/two-factor-challenge', ['code' => $wrong])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        [$user, $recovery] = $this->personWithTwoFactor();

        $this->passwordStep($user);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes(), 'a used code is replaced');

        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->passwordStep($user);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_the_challenge_page_is_not_open_without_the_password_step(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');
    }
}
