<?php

namespace Tests\Feature\Oidc;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

/** R-20 §10: RP-initiated end-session, back-channel logout, and central revocation on logout and password reset. */
class LogoutTest extends OidcTestCase
{
    public function test_end_session_logs_out_centrally_and_returns_only_to_a_registered_uri(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $result = $this->completeCodeFlow($user);

        $response = $this->actingAs($user)->get('/oauth/end-session?'.http_build_query([
            'id_token_hint' => $result['id_token'],
            'post_logout_redirect_uri' => 'https://central.test/sso/logged-out',
            'state' => 'bye-123',
        ]));

        $response->assertRedirect('https://central.test/sso/logged-out?state=bye-123');
        $this->assertGuest();
    }

    public function test_an_unregistered_post_logout_uri_is_ignored(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $result = $this->completeCodeFlow($user);

        $this->actingAs($user)->get('/oauth/end-session?'.http_build_query([
            'id_token_hint' => $result['id_token'],
            'post_logout_redirect_uri' => 'https://evil.example/phish',
        ]))->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_central_logout_sends_a_valid_backchannel_logout_token_and_revokes_tokens(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $this->completeCodeFlow($user);

        // A second platform signed in too; it must also be told. (The initiating flow here is
        // InfoDot's own logout, so every platform with a session gets the token.)
        $this->post('/logout');

        $recorded = Http::recorded(fn ($request) => $request->url() === 'https://central.test/sso/backchannel-logout');
        $this->assertCount(1, $recorded, 'the signed-in platform received exactly one back-channel logout');

        $logoutToken = $recorded->first()[0]['logout_token'];
        $token = (new Parser(new JoseEncoder))->parse($logoutToken);
        assert($token instanceof Plain);

        $publicKey = InMemory::file(Passport::keyPath('oauth-public.key'));
        $this->assertTrue((new Validator)->validate($token, new SignedWith(new Sha256, $publicKey)));
        $this->assertSame((string) $user->uuid, $token->claims()->get('sub'));
        $this->assertTrue($token->isPermittedFor($this->platform->oauth_client_id));
        $this->assertArrayHasKey('http://schemas.openid.net/event/backchannel-logout', $token->claims()->get('events'));
        $this->assertFalse($token->claims()->has('nonce'), 'a logout token must not carry a nonce');

        // Provider-side credentials are revoked and the session registry is cleared.
        $this->assertSame(0, Token::where('user_id', $user->id)->where('revoked', false)->count());
        $this->assertDatabaseMissing('sso_platform_sessions', ['user_id' => $user->id]);
    }

    public function test_a_password_reset_propagates_a_central_logout(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $this->completeCodeFlow($user);

        event(new PasswordReset($user));
        app(Kernel::class)->terminate(request(), response('')); // run afterResponse jobs

        $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), 'backchannel-logout')));
    }
}
