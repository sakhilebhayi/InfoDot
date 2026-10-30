<?php

namespace Tests\Feature\Oidc;

use App\Models\DotPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-20 §4 discovery metadata and §2 the platform registry command. */
class DiscoveryAndRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_states_exactly_what_the_server_enforces(): void
    {
        $doc = $this->getJson('/.well-known/openid-configuration')->assertOk()->json();

        $this->assertSame(['code'], $doc['response_types_supported'], 'code flow only');
        $this->assertSame(['S256'], $doc['code_challenge_methods_supported'], 'PKCE S256 only, plain is not offered');
        $this->assertSame(['RS256'], $doc['id_token_signing_alg_values_supported']);
        $this->assertTrue($doc['backchannel_logout_supported']);
        $this->assertStringEndsWith('/oauth/authorize', $doc['authorization_endpoint']);
        $this->assertStringEndsWith('/oauth/token', $doc['token_endpoint']);
        $this->assertStringEndsWith('/oauth/jwks', $doc['jwks_uri']);
        $this->assertStringEndsWith('/oauth/end-session', $doc['end_session_endpoint']);
        $this->assertStringEndsWith('/oauth/userinfo', $doc['userinfo_endpoint']);
        $this->assertContains('openid', $doc['scopes_supported']);
        $this->assertSame($doc['issuer'], rtrim(url('/'), '/'));
    }

    public function test_the_registry_command_registers_suspends_and_reactivates_a_platform(): void
    {
        $this->artisan('sso:platform', [
            'action' => 'register', 'slug' => 'central',
            '--name' => 'Dot.Central', '--domain' => 'https://central.infodot.co.za',
            '--redirect' => ['https://central.infodot.co.za/sso/callback'],
            '--post-logout' => ['https://central.infodot.co.za/sso/logged-out'],
            '--backchannel' => 'https://central.infodot.co.za/sso/backchannel-logout',
        ])->expectsOutputToContain('DOTSSO_CLIENT_ID=')->assertSuccessful();

        $platform = DotPlatform::where('slug', 'central')->firstOrFail();
        $this->assertSame('active', $platform->status);
        $this->assertDatabaseHas('oauth_clients', ['id' => $platform->oauth_client_id, 'name' => 'Dot.Central']);

        $this->artisan('sso:platform', ['action' => 'suspend', 'slug' => 'central'])->assertSuccessful();
        $this->assertSame('suspended', $platform->fresh()->status);

        $this->artisan('sso:platform', ['action' => 'activate', 'slug' => 'central'])->assertSuccessful();
        $this->assertSame('active', $platform->fresh()->status);
    }

    public function test_the_registry_refuses_plain_http_redirects_for_real_hosts(): void
    {
        $this->artisan('sso:platform', [
            'action' => 'register', 'slug' => 'bad',
            '--name' => 'Bad', '--domain' => 'https://bad.infodot.co.za',
            '--redirect' => ['http://bad.infodot.co.za/sso/callback'],
        ])->assertFailed();

        $this->assertDatabaseMissing('dot_platforms', ['slug' => 'bad']);
    }
}
