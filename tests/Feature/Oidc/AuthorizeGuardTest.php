<?php

namespace Tests\Feature\Oidc;

use App\Models\User;

/** R-20 §7: the authorize endpoint's house rules — reject before anything is processed, never via the redirect_uri. */
class AuthorizeGuardTest extends OidcTestCase
{
    private function authorizeExpecting400(array $overrides, User $user): void
    {
        [, $challenge] = $this->pkcePair();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge, $overrides)))
            ->assertStatus(400);
    }

    public function test_every_malformed_authorize_request_is_rejected_with_400(): void
    {
        $user = User::factory()->create();

        $this->authorizeExpecting400(['client_id' => 'not-registered'], $user);
        $this->authorizeExpecting400(['response_type' => 'token'], $user);
        $this->authorizeExpecting400(['scope' => 'profile email'], $user);           // openid missing
        $this->authorizeExpecting400(['state' => 'short'], $user);
        $this->authorizeExpecting400(['state' => ''], $user);
        $this->authorizeExpecting400(['nonce' => ''], $user);
        $this->authorizeExpecting400(['code_challenge' => ''], $user);
        $this->authorizeExpecting400(['code_challenge' => 'too-short'], $user);
        $this->authorizeExpecting400(['code_challenge_method' => 'plain'], $user);
    }

    public function test_a_suspended_platform_is_rejected_at_authorize(): void
    {
        $this->platform->update(['status' => 'suspended']);

        $this->authorizeExpecting400([], User::factory()->create());
    }

    public function test_an_unregistered_redirect_uri_is_never_redirected_to(): void
    {
        $user = User::factory()->create();
        [, $challenge] = $this->pkcePair();

        $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query(
            $this->authorizeQuery($challenge, ['redirect_uri' => 'https://evil.example/callback'])
        ));

        $this->assertTrue($response->getStatusCode() >= 400, 'the request fails outright');
        $this->assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'), 'no browser redirect to an unregistered URI');
    }

    public function test_a_guest_is_sent_to_the_central_login_and_returns_to_the_authorize_url(): void
    {
        [, $challenge] = $this->pkcePair();
        $url = '/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge));

        $this->get($url)->assertRedirect(route('login'));

        // After signing in, the intended URL continues the platform's sign-in.
        $this->assertStringContainsString('oauth/authorize', (string) session('url.intended'));
    }

    public function test_an_unverified_account_must_verify_email_before_any_platform_sign_in(): void
    {
        $user = User::factory()->unverified()->create();
        [, $challenge] = $this->pkcePair();

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)))
            ->assertRedirect(route('verification.notice'));
    }
}
