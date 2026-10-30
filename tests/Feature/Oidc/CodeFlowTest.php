<?php

namespace Tests\Feature\Oidc;

use App\Models\User;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

/** R-20 §18 login + token security: the full OpenID Connect code flow, and every forgery rejected. */
class CodeFlowTest extends OidcTestCase
{
    public function test_a_registered_platform_gets_a_valid_id_token_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();

        $result = $this->completeCodeFlow($user);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('id_token', $result);
        $this->assertSame('Bearer', $result['token_type']);

        $token = (new Parser(new JoseEncoder))->parse($result['id_token']);
        assert($token instanceof Plain);

        $publicKey = InMemory::file(Passport::keyPath('oauth-public.key'));
        $this->assertTrue((new Validator)->validate($token, new SignedWith(new Sha256, $publicKey)), 'id_token is RS256-signed with the provider key');

        $this->assertSame((string) $user->uuid, $token->claims()->get('sub'), 'sub is the ecosystem uuid, never the numeric id or email');
        $this->assertTrue($token->isPermittedFor($this->platform->oauth_client_id), 'aud is the requesting client');
        $this->assertSame('nonce-123456', $token->claims()->get('nonce'), 'the nonce round-trips into the id_token');
        $this->assertSame($user->email, $token->claims()->get('email'));
        $this->assertTrue($token->claims()->get('email_verified'));
        $this->assertFalse($token->isExpired(now()->toDateTimeImmutable()));
        $this->assertSame(config('openid.token_headers.kid'), $token->headers()->get('kid'));

        // The sign-in is recorded for the connected-platforms view and logout propagation.
        $this->assertDatabaseHas('sso_platform_sessions', ['user_id' => $user->id, 'dot_platform_id' => $this->platform->id]);
        $this->assertNotNull($this->platform->fresh()->last_authenticated_at);
    }

    public function test_the_access_token_reads_userinfo_with_the_uuid_subject(): void
    {
        $user = User::factory()->create();
        $result = $this->completeCodeFlow($user);

        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$result['access_token']])
            ->assertOk()
            ->assertJson([
                'sub' => (string) $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => true,
            ]);
    }

    public function test_userinfo_rejects_requests_without_a_token(): void
    {
        $this->getJson('/oauth/userinfo')->assertUnauthorized();
    }

    public function test_an_authorization_code_is_single_use(): void
    {
        $user = User::factory()->create();
        $result = $this->completeCodeFlow($user);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => 'https://central.test/sso/callback',
            'code' => $result['code'],
            'code_verifier' => $result['verifier'],
        ])->assertStatus(400);
    }

    public function test_a_wrong_pkce_verifier_is_rejected(): void
    {
        $user = User::factory()->create();
        [, $challenge] = $this->pkcePair();

        $redirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)))
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $params);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => 'https://central.test/sso/callback',
            'code' => $params['code'],
            'code_verifier' => str_repeat('wrong-verifier~', 4),
        ])->assertStatus(400);
    }

    public function test_a_wrong_client_secret_is_rejected(): void
    {
        $user = User::factory()->create();
        [$verifier, $challenge] = $this->pkcePair();

        $redirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)))
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $params);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => 'not-the-secret',
            'redirect_uri' => 'https://central.test/sso/callback',
            'code' => $params['code'],
            'code_verifier' => $verifier,
        ])->assertStatus(401);
    }

    public function test_a_suspended_platform_cannot_exchange_codes(): void
    {
        $this->platform->update(['status' => 'suspended']);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => 'https://central.test/sso/callback',
            'code' => 'anything',
            'code_verifier' => 'anything',
        ])->assertStatus(401)->assertJson(['error' => 'invalid_client']);
    }

    public function test_the_jwks_publishes_the_signing_key_that_verifies_id_tokens(): void
    {
        $jwks = $this->getJson('/oauth/jwks')->assertOk()->json();

        $details = openssl_pkey_get_details(openssl_pkey_get_public('file://'.Passport::keyPath('oauth-public.key')));
        $expectedModulus = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');

        $this->assertSame('RS256', $jwks['keys'][0]['alg']);
        $this->assertSame($expectedModulus, $jwks['keys'][0]['n'], 'JWKS modulus matches the real public key');
        $this->assertSame(config('openid.token_headers.kid'), $jwks['keys'][0]['kid']);
    }
}
