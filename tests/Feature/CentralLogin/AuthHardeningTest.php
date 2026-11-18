<?php

namespace Tests\Feature\CentralLogin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/** R-54: rate limits on the way-in endpoints, CSRF, cookie flags, and the checks that nothing here can be steered off-site. */
class AuthHardeningTest extends CentralLoginTestCase
{
    public function test_login_attempts_share_one_counter_whatever_the_case_or_spacing_of_the_address(): void
    {
        User::factory()->create(['email' => 'pat@example.test']);

        foreach (['pat@example.test', 'PAT@example.test', 'Pat@Example.test ', 'pAt@example.test', 'PaT@EXAMPLE.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong-one']);
        }

        $this->post('/login', ['email' => 'PAT@EXAMPLE.TEST', 'password' => 'wrong-one'])->assertStatus(429);
    }

    public function test_one_account_cannot_be_guessed_at_from_many_addresses(): void
    {
        User::factory()->create(['email' => 'target@example.test']);

        $blocked = false;
        for ($i = 1; $i <= 25; $i++) {
            $status = $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.$i])->post('/login', ['email' => 'target@example.test', 'password' => 'wrong-'.$i])->getStatusCode();
            $blocked = $blocked || $status === 429;
        }

        $this->assertTrue($blocked, 'after twenty tries on one account in fifteen minutes the account is throttled, whatever the source');
    }

    public function test_reset_mail_for_one_address_is_limited_across_sources(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'victim@example.test']);

        $statuses = [];
        for ($i = 1; $i <= 8; $i++) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.'.$i])->post('/forgot-password', ['email' => 'Victim@Example.test'])->getStatusCode();
        }

        $this->assertContains(429, $statuses);
        $this->assertSame(429, end($statuses));
    }

    public function test_a_different_address_is_not_affected_by_anothers_limit(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.'.$i])->post('/forgot-password', ['email' => 'one@example.test']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.3.9.9'])->post('/forgot-password', ['email' => 'two@example.test'])->assertStatus(302);
    }

    public function test_registration_and_verification_resend_stay_limited(): void
    {
        $user = $this->newcomer(verified: false);

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post('/email/verification-notification');
        }

        $this->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_csrf_protection_covers_every_way_in_page(): void
    {
        $except = (new \ReflectionProperty(VerifyCsrfToken::class, 'except'))->getValue(app(VerifyCsrfToken::class));

        foreach ($except as $path) {
            $this->assertStringNotContainsString('login', $path);
            $this->assertStringNotContainsString('register', $path);
            $this->assertStringNotContainsString('password', $path);
            $this->assertStringNotContainsString('verif', $path);
            $this->assertStringNotContainsString('onboarding', $path);
            $this->assertStringNotContainsString('oauth/authorize', $path);
        }

        $names = ['login', 'register', 'password.email', 'password.update', 'verification.send', 'onboarding.business.store'];
        foreach ($names as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('web', $route->gatherMiddleware(), $name.' sits in the web group, which carries CSRF and the session');
        }
    }

    public function test_session_cookie_flags(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertNull(config('session.domain') ?? null, 'host-only: the session is never shared with another host');
        $this->assertSame(120, (int) config('session.lifetime'));

        $source = file_get_contents(config_path('session.php'));
        $this->assertStringContainsString("'secure' => env('SESSION_SECURE_COOKIE'", $source);
    }

    public function test_no_way_in_page_reads_a_redirect_or_platform_parameter(): void
    {
        $this->get('/login?redirect=https://evil.example&return_to=https://evil.example&redirect_uri=https://evil.example')->assertOk();
        $user = User::factory()->create(['email' => 'safe@example.test']);

        $this->post('/login?redirect=https://evil.example&next=https://evil.example', ['email' => 'safe@example.test', 'password' => 'password', 'redirect' => 'https://evil.example', 'return_to' => 'https://evil.example'])
            ->assertRedirect('/home');
        $this->assertNotNull($user);
    }

    public function test_a_platform_cannot_borrow_another_platforms_pending_request(): void
    {
        // A client id that exists but was suspended: the page falls back and the authorize endpoint refuses outright.
        $this->platform->update(['status' => 'suspended']);

        $this->get($this->authorizeUrl())->assertStatus(400);
        $this->get('/login')->assertViewHas('platformBranding', null);
    }

    public function test_a_bad_authorize_request_is_refused_before_a_guest_is_sent_to_sign_in(): void
    {
        $this->get($this->authorizeUrl(['redirect_uri' => 'https://evil.example/cb', 'state' => 'x']))->assertStatus(400);
        $this->assertNull(session('url.intended'), 'a rejected request is never parked as the place to return to');
    }
}
