<?php

namespace Tests\Feature\CentralLogin;

use App\Models\DotPlatform;
use App\Services\Sso\PlatformContext;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\ClientRepository;

/**
 * R-54: the originating platform is taken ONLY from a valid pending /oauth/authorize request held in the session. A
 * query parameter on /login or /register is never believed, and anything off in the pending request means "no platform".
 */
class PlatformContextTest extends CentralLoginTestCase
{
    public function test_a_signed_out_authorize_request_brands_the_sign_in_and_registration_pages(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central' && $b->name === 'Dot.Central');
        $this->get('/register')->assertOk()->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central');
        $this->get('/forgot-password')->assertOk()->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central');
    }

    public function test_a_direct_visit_is_the_standard_page(): void
    {
        $this->get('/login')->assertOk()->assertViewHas('platformBranding', null);
        $this->get('/register')->assertOk()->assertViewHas('platformBranding', null);
    }

    public function test_a_platform_or_client_query_parameter_is_never_believed(): void
    {
        $this->get('/login?platform=central')->assertViewHas('platformBranding', null);
        $this->get('/login?client='.$this->platform->oauth_client_id)->assertViewHas('platformBranding', null);
        $this->get('/register?client_id='.$this->platform->oauth_client_id.'&platform=central')->assertViewHas('platformBranding', null);
        $this->get('/register?platform[]=central')->assertViewHas('platformBranding', null);
    }

    public function test_a_spoofed_parameter_cannot_override_the_real_pending_platform(): void
    {
        $other = $this->registerOtherPlatform();

        $this->get($this->authorizeUrl());
        $this->get('/login?platform=design&client='.$other->oauth_client_id)->assertViewHas('platformBranding', fn ($b) => $b->slug === 'central');
    }

    public function test_unknown_inactive_and_tampered_requests_resolve_to_no_platform(): void
    {
        $bad = [
            'unknown client' => $this->authorizeUrl(['client_id' => 'no-such-client']),
            'unregistered redirect' => $this->authorizeUrl(['redirect_uri' => 'https://evil.example/sso/callback']),
            'redirect with extra path' => $this->authorizeUrl(['redirect_uri' => 'https://central.test/sso/callback/../evil']),
            'redirect on a sibling host' => $this->authorizeUrl(['redirect_uri' => 'https://central.test.evil.example/sso/callback']),
            'short state' => $this->authorizeUrl(['state' => 'short']),
            'no nonce' => $this->authorizeUrl(['nonce' => '']),
            'plain pkce' => $this->authorizeUrl(['code_challenge_method' => 'plain']),
            'no openid scope' => $this->authorizeUrl(['scope' => 'profile']),
            'token response type' => $this->authorizeUrl(['response_type' => 'token']),
        ];

        foreach ($bad as $label => $url) {
            $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://localhost'.$url])->get('/login')->assertViewHas('platformBranding', null, 'must be ignored: '.$label);
        }
    }

    public function test_a_suspended_platform_resolves_to_no_platform(): void
    {
        $this->platform->update(['status' => 'suspended']);

        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://localhost'.$this->authorizeUrl()])->get('/login')->assertViewHas('platformBranding', null);
    }

    public function test_a_pending_address_on_another_host_or_another_path_is_not_followed(): void
    {
        $url = $this->authorizeUrl();

        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'https://evil.example'.$url])->get('/login')->assertViewHas('platformBranding', null);
        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://localhost/home?x='.urlencode($url)])->get('/login')->assertViewHas('platformBranding', null);
        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://localhost/oauth/authorize/../home'])->get('/login')->assertViewHas('platformBranding', null);
        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://user:pw@localhost'.$url])->get('/login')->assertViewHas('platformBranding', null);
        $this->withSession(['sso.pending_at' => time(), 'url.intended' => ['array']])->get('/login')->assertViewHas('platformBranding', null);
    }

    public function test_an_ordinary_intended_page_is_the_standard_page(): void
    {
        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://localhost/inbox'])->get('/login')->assertViewHas('platformBranding', null);
    }

    public function test_the_log_line_carries_a_reason_only(): void
    {
        Log::spy();

        $this->withSession(['sso.pending_at' => time(), 'url.intended' => 'http://localhost'.$this->authorizeUrl(['redirect_uri' => 'https://evil.example/cb'])])->get('/login');

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) {
            return $message === 'sso.pending_ignored' && array_keys($context) === ['reason'] && $context['reason'] === 'redirect_uri_not_registered';
        })->atLeast()->once();
    }

    public function test_a_valid_request_is_remembered_across_the_detour_and_forgotten_when_it_completes(): void
    {
        $user = $this->entitledUser();

        $this->get($this->authorizeUrl());
        $this->assertStringStartsWith('http://localhost/oauth/authorize?', (string) session(PlatformContext::SESSION_KEY));
        $this->assertStringContainsString('client_id='.$this->platform->oauth_client_id, (string) session(PlatformContext::SESSION_KEY));

        // The person signs in; the request continues and the code goes back to the platform.
        $response = $this->actingAs($user)->get($this->authorizeUrl())->assertRedirect();
        $this->assertStringStartsWith('https://central.test/sso/callback?', (string) $response->headers->get('Location'));
        $this->assertNull(session(PlatformContext::SESSION_KEY));
    }

    public function test_an_inactive_branding_row_still_names_the_platform_from_the_registry(): void
    {
        $this->get($this->authorizeUrl());

        $this->get('/login')->assertViewHas('platformBranding', fn ($b) => $b->name === 'Dot.Central' && $b->version === 0);
    }

    public function test_someone_already_signed_in_who_opens_sign_in_continues_the_pending_request(): void
    {
        $user = $this->entitledUser();
        $this->get($this->authorizeUrl());

        $pending = (string) session(PlatformContext::SESSION_KEY);
        $this->assertNotSame('', $pending);
        $this->actingAs($user)->get('/login')->assertRedirect($pending);
        $this->flushSession();
        $this->actingAs($user)->withSession(['sso.pending_at' => time(), 'url.intended' => 'https://evil.example/oauth/authorize?x=1'])->get('/register')->assertRedirect('/home');
    }

    private function registerOtherPlatform(): DotPlatform
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Dot.Design', ['https://design.test/sso/callback'], confidential: true);

        return DotPlatform::create([
            'name' => 'Dot.Design', 'slug' => 'design', 'domain' => 'https://design.test', 'oauth_client_id' => $client->getKey(),
            'redirect_uris' => ['https://design.test/sso/callback'], 'scopes' => ['openid'], 'status' => 'active',
        ]);
    }

    public function test_a_sign_in_started_long_ago_does_not_brand_a_later_direct_visit(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect('/login');
        $this->get('/login')->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central');

        // The person wandered off; an hour later they open InfoDot's own sign-in in the same browser.
        $this->withSession([PlatformContext::SESSION_AT_KEY => time() - PlatformContext::FRESH_FOR_SECONDS - 60])
            ->get('/login')->assertOk()->assertViewHas('platformBranding', null);

        $this->assertNull(session(PlatformContext::SESSION_KEY), 'the stale request is dropped');
        $this->assertNull(session('url.intended'), 'and so is the place it would have sent them after signing in');
    }

    public function test_a_pending_address_with_no_time_on_it_is_not_believed(): void
    {
        $this->withSession(['url.intended' => 'http://localhost'.$this->authorizeUrl()])->get('/login')->assertViewHas('platformBranding', null);
    }

    public function test_use_infodot_on_its_own_drops_the_platform_and_shows_the_standard_page(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Use InfoDot on its own');

        $this->get('/login/infodot')->assertRedirect(route('login'));

        $this->get('/login')->assertOk()->assertViewHas('platformBranding', null)->assertDontSee('Use InfoDot on its own');
        $this->assertNull(session(PlatformContext::SESSION_KEY));
        $this->assertNull(session('url.intended'));
    }

    public function test_the_direct_page_offers_no_way_out_of_a_platform_it_is_not_showing(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Use InfoDot on its own');
    }
}
