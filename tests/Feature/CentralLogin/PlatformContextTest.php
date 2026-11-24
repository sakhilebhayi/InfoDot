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
        $f = $this->flowId();

        $this->get('/login?f='.$f)->assertOk()->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central' && $b->name === 'Dot.Central');
        $this->get('/register?f='.$f)->assertOk()->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central');
        $this->get('/forgot-password?f='.$f)->assertOk()->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central');
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

        $f = $this->flowId();
        $this->get('/login?f='.$f.'&platform=design&client='.$other->oauth_client_id)->assertViewHas('platformBranding', fn ($b) => $b->slug === 'central');
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
            $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://localhost'.$url])->assertViewHas('platformBranding', null, 'must be ignored: '.$label);
        }
    }

    public function test_a_suspended_platform_resolves_to_no_platform(): void
    {
        $this->platform->update(['status' => 'suspended']);

        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://localhost'.$this->authorizeUrl()])->assertViewHas('platformBranding', null);
    }

    public function test_a_pending_address_on_another_host_or_another_path_is_not_followed(): void
    {
        $url = $this->authorizeUrl();

        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'https://evil.example'.$url])->assertViewHas('platformBranding', null);
        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://localhost/home?x='.urlencode($url)])->assertViewHas('platformBranding', null);
        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://localhost/oauth/authorize/../home'])->assertViewHas('platformBranding', null);
        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://user:pw@localhost'.$url])->assertViewHas('platformBranding', null);
        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => ['array']])->assertViewHas('platformBranding', null);
    }

    public function test_an_ordinary_intended_page_is_the_standard_page(): void
    {
        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://localhost/inbox'])->assertViewHas('platformBranding', null);
    }

    public function test_the_log_line_carries_a_reason_only(): void
    {
        Log::spy();

        $this->pendingPage(['sso.pending_at' => time(), 'url.intended' => 'http://localhost'.$this->authorizeUrl(['redirect_uri' => 'https://evil.example/cb'])]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) {
            return $message === 'sso.pending_ignored' && array_keys($context) === ['reason'] && $context['reason'] === 'redirect_uri_not_registered';
        })->atLeast()->once();
    }

    public function test_a_valid_request_is_remembered_across_the_detour_and_forgotten_when_it_completes(): void
    {
        $user = $this->entitledUser();

        $this->flowId();
        $this->assertStringStartsWith('http://localhost/oauth/authorize?', (string) session(PlatformContext::SESSION_KEY));
        $this->assertStringContainsString('client_id='.$this->platform->oauth_client_id, (string) session(PlatformContext::SESSION_KEY));

        // The person signs in; the request continues and the code goes back to the platform.
        $response = $this->actingAs($user)->get($this->authorizeUrl())->assertRedirect();
        $this->assertStringStartsWith('https://central.test/sso/callback?', (string) $response->headers->get('Location'));
        $this->assertNull(session(PlatformContext::SESSION_KEY));
    }

    public function test_an_inactive_branding_row_still_names_the_platform_from_the_registry(): void
    {
        $f = $this->flowId();

        $this->get('/login?f='.$f)->assertViewHas('platformBranding', fn ($b) => $b->name === 'Dot.Central' && $b->version === 0);
    }

    public function test_someone_already_signed_in_who_opens_sign_in_continues_the_pending_request(): void
    {
        $user = $this->entitledUser();
        $f = $this->flowId();

        $pending = (string) session(PlatformContext::SESSION_KEY);
        $this->assertNotSame('', $pending);
        $this->actingAs($user)->get('/login?f='.$f)->assertRedirect($pending);
        $this->flushSession();
        $this->actingAs($user)->withSession(['sso.pending_at' => time(), 'url.intended' => 'https://evil.example/oauth/authorize?x=1'])->get('/register')->assertRedirect('/home');
    }

    /** A page that is not one of the way-in pages (it has no flow id) and takes the pending request from the session: the verification notice. */
    private function pendingPage(array $session)
    {
        return $this->actingAs($this->newcomer(verified: false))->withSession($session)->get('/email/verify');
    }

    private function registerOtherPlatform(): DotPlatform
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Dot.Design', ['https://design.test/sso/callback'], confidential: true);

        return DotPlatform::create([
            'name' => 'Dot.Design', 'slug' => 'design', 'domain' => 'https://design.test', 'oauth_client_id' => $client->getKey(),
            'redirect_uris' => ['https://design.test/sso/callback'], 'scopes' => ['openid'], 'status' => 'active',
        ]);
    }

    public function test_a_page_opened_without_a_flow_id_is_infodots_own_even_right_after_a_platform_started_a_sign_in(): void
    {
        $f = $this->flowId();
        $this->get('/login?f='.$f)->assertViewHas('platformBranding', fn ($b) => $b !== null && $b->slug === 'central');

        // Straight away, the person opens InfoDot's own sign-in in the same browser.
        $this->get('/login')->assertOk()->assertViewHas('platformBranding', null)->assertDontSee('Use InfoDot on its own');
        $this->get('/register')->assertOk()->assertViewHas('platformBranding', null);
        $this->assertNull(session(PlatformContext::SESSION_KEY), 'the platform sign-in is no longer the browser\'s pending one');
        $this->assertNull(session('url.intended'), 'so signing in here does not carry on to the platform');
    }

    public function test_signing_in_directly_goes_to_infodot_not_to_the_platform_started_earlier(): void
    {
        $user = $this->entitledUser();
        $this->flowId();

        $password = 'Pw-'.bin2hex(random_bytes(10)).'-Aa1';
        $user->forceFill(['password' => bcrypt($password)])->save();

        $this->get('/login')->assertOk();
        $response = $this->post('/login', ['email' => $user->email, 'password' => $password]);

        $this->assertStringNotContainsString('/oauth/authorize', (string) $response->headers->get('Location'), 'a direct sign-in is not sent on to the platform');
    }

    public function test_two_tabs_each_keep_their_own_flow_and_a_plain_tab_cannot_end_the_other(): void
    {
        $f = $this->flowId();
        $other = $this->registerOtherPlatform();
        $g = $this->flowId($this->authorizeUrl(['client_id' => $other->oauth_client_id, 'redirect_uri' => 'https://design.test/sso/callback']));

        $this->assertNotSame($f, $g);
        $this->get('/login?f='.$f)->assertViewHas('platformBranding', fn ($b) => $b->slug === 'central');
        $this->get('/login?f='.$g)->assertViewHas('platformBranding', fn ($b) => $b->slug === 'design');

        // A plain tab in between does not take either flow away: each page still works with its own id.
        $this->get('/login')->assertViewHas('platformBranding', null);
        $this->get('/login?f='.$f)->assertViewHas('platformBranding', fn ($b) => $b->slug === 'central');
    }

    public function test_the_branded_forms_and_links_carry_the_flow_id_and_the_plain_ones_do_not(): void
    {
        $f = $this->flowId();

        $branded = $this->get('/login?f='.$f)->getContent();
        $this->assertStringContainsString('name="f" value="'.$f.'"', $branded);
        $this->assertStringContainsString('/register?f='.$f, $branded);
        $this->assertStringContainsString('/forgot-password?f='.$f, $branded);
        $this->assertStringContainsString('Use InfoDot on its own', $branded);

        $this->assertStringContainsString('name="f" value="'.$f.'"', $this->get('/register?f='.$f)->getContent());

        $plain = $this->get('/login')->getContent();
        $this->assertStringNotContainsString('name="f"', $plain);
        $this->assertStringNotContainsString('?f=', $plain);
    }

    public function test_a_made_up_or_expired_flow_id_is_the_standard_page(): void
    {
        $this->get('/login?f=aaaaaaaaaaaaaaaaaaaa')->assertViewHas('platformBranding', null);
        $this->get('/login?f[]=x')->assertViewHas('platformBranding', null);

        $f = $this->flowId();
        $flows = session(PlatformContext::SESSION_FLOWS);
        $flows[$f]['at'] = time() - PlatformContext::FRESH_FOR_SECONDS - 60;
        $this->withSession([PlatformContext::SESSION_FLOWS => $flows])->get('/login?f='.$f)->assertViewHas('platformBranding', null);
    }

    public function test_the_page_without_a_flow_id_does_not_offer_a_way_out_of_a_platform_it_is_not_showing(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Use InfoDot on its own');
    }

    public function test_a_pending_address_with_no_time_on_it_is_not_believed_by_the_other_pages(): void
    {
        $this->withSession(['url.intended' => 'http://localhost'.$this->authorizeUrl()])->get('/email/verify')->assertRedirect();
    }

    public function test_the_infodot_only_route_still_works_for_old_links(): void
    {
        $this->flowId();
        $this->get('/login/infodot')->assertRedirect(route('login'));
        $this->assertNull(session(PlatformContext::SESSION_KEY));
    }
}
