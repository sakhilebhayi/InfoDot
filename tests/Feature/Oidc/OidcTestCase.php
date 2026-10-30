<?php

namespace Tests\Feature\Oidc;

use App\Models\DotPlatform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/** Shared plumbing for the R-20 identity-provider suite: a registered platform and the PKCE dance. */
abstract class OidcTestCase extends TestCase
{
    use RefreshDatabase;

    protected DotPlatform $platform;

    protected string $clientSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Dot.Central', ['https://central.test/sso/callback'], confidential: true,
        );

        $this->clientSecret = $client->plainSecret;

        $this->platform = DotPlatform::create([
            'name' => 'Dot.Central',
            'slug' => 'central',
            'domain' => 'https://central.test',
            'oauth_client_id' => $client->getKey(),
            'redirect_uris' => ['https://central.test/sso/callback'],
            'post_logout_redirect_uris' => ['https://central.test/sso/logged-out'],
            'backchannel_logout_uri' => 'https://central.test/sso/backchannel-logout',
            'scopes' => ['openid', 'profile', 'email'],
            'status' => 'active',
        ]);
    }

    /** @return array{0: string, 1: string} verifier and S256 challenge */
    protected function pkcePair(): array
    {
        $verifier = str_repeat('a1B2-c3._~', 6).'xYz9';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [$verifier, $challenge];
    }

    /** @param array<string, string> $overrides */
    protected function authorizeQuery(string $challenge, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->platform->oauth_client_id,
            'redirect_uri' => 'https://central.test/sso/callback',
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => str_repeat('s', 40),
            'nonce' => 'nonce-123456',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], $overrides);
    }

    /** Runs the whole code flow for $user and returns the token-endpoint JSON. */
    protected function completeCodeFlow(User $user): array
    {
        [$verifier, $challenge] = $this->pkcePair();

        $redirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://central.test/sso/callback?', (string) $redirect);
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $params);
        $this->assertSame(str_repeat('s', 40), $params['state'], 'state echoes back unchanged');

        $response = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => 'https://central.test/sso/callback',
            'code' => $params['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        return ['code' => $params['code'], 'verifier' => $verifier] + $response->json();
    }
}
