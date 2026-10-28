<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The sign-in credential for another platform must never be in a URL: not in the address bar, history,
 * access logs or Referer, and not left on screen when the other platform refuses the sign-in.
 */
class PlatformLaunchTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function live(): void
    {
        config(['ecosystem.platforms.files.url' => 'https://files.infodot.co.za', 'ecosystem.platforms.files.active' => true]);
    }

    public function test_the_launch_page_posts_a_single_use_token_to_the_platform_and_shows_it_nowhere(): void
    {
        $this->live();
        $user = $this->user();

        $response = $this->actingAs($user)->get('/go/files');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('#<form id="launch" method="post" action="https://files\.infodot\.co\.za/auth/ecosystem">#', $html);
        $this->assertStringContainsString('<input type="hidden" name="token" value="', $html);
        $this->assertStringNotContainsString('?token=', $html);
        $this->assertNull($response->headers->get('Location'), 'the page is the handoff: no redirect carrying the token');

        // The hidden value is a real token for the platform, expiring in about a minute.
        preg_match('#name="token" value="([^"]+)"#', $html, $m);
        $token = PersonalAccessToken::findToken(html_entity_decode($m[1]));
        $this->assertNotNull($token);
        $this->assertSame('ecosystem-handoff:files', $token->name);
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addSeconds(61)));
        $this->assertSame($user->id, $token->tokenable_id);

        // Not visible text: the token is only inside the hidden input.
        $visible = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html));
        $this->assertStringNotContainsString(html_entity_decode($m[1]), $visible);
    }

    public function test_the_launch_page_is_not_cached_and_only_lets_the_form_go_to_the_destination(): void
    {
        $this->live();

        $response = $this->actingAs($this->user())->get('/go/files');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('form-action https://files.infodot.co.za', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_an_in_platform_path_is_passed_on_in_the_form_and_anything_else_is_dropped(): void
    {
        $this->live();
        $user = $this->user();

        $this->assertStringContainsString('name="redirect" value="/o/abc"', $this->actingAs($user)->get('/go/files?redirect='.rawurlencode('/o/abc'))->getContent());

        foreach (['//evil.example', 'https://evil.example', '/a/../b', '/a//b', '\\evil', '/a%2F%2Fb', "/x\ny"] as $bad) {
            $this->assertStringNotContainsString('name="redirect"', $this->actingAs($user)->get('/go/files?redirect='.rawurlencode($bad))->getContent(), "should drop [{$bad}]");
        }
    }

    public function test_a_refused_destination_gets_no_token_and_the_page_says_so(): void
    {
        config(['ecosystem.platforms.files.url' => 'https://files.infodot.app']);

        $this->actingAs($this->user())->get('/go/files')->assertStatus(503);

        config(['ecosystem.platforms.files.url' => 'https://files.infodot.co.za', 'ecosystem.platforms.files.active' => false]);
        $this->actingAs($this->user())->get('/go/files')->assertStatus(503);

        $this->actingAs($this->user())->get('/go/nope')->assertStatus(503);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_guest_is_sent_to_sign_in_and_gets_no_token(): void
    {
        $this->live();

        $this->get('/go/files')->assertRedirect();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_infodots_own_endpoint_signs_in_from_a_post_and_spends_the_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->addMinute())->plainTextToken;

        $this->post('/auth/ecosystem', ['token' => $token], ['Origin' => 'http://files.localhost'])
            ->assertRedirect(route('solutions'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_post_from_another_site_is_refused_without_spending_or_echoing_the_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->addMinute())->plainTextToken;

        $response = $this->post('/auth/ecosystem', ['token' => $token], ['Origin' => 'https://evil.example']);

        $response->assertStatus(303)->assertRedirect('/login');
        $this->assertGuest();
        $this->assertStringNotContainsString($token, (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString($token, (string) $response->getContent());
        $this->assertNull(session('token'));
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_a_bad_or_expired_token_lands_on_the_plain_login_page_and_nothing_is_echoed(): void
    {
        $user = $this->user();
        $expired = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->subMinute())->plainTextToken;

        foreach ([$expired, 'not-a-token', ''] as $token) {
            $response = $this->post('/auth/ecosystem', ['token' => $token], ['Origin' => 'http://files.localhost']);

            $response->assertStatus(303)->assertRedirect('/login');
            $this->assertGuest();
            $location = (string) $response->headers->get('Location');
            $this->assertStringNotContainsString('token', $location);
            if ($token !== '') {
                $this->assertStringNotContainsString($token, $location.$response->getContent());
            }
        }

        // A refused GET (the old link shape) is also a clean redirect: the person is not left on a URL with a token in it.
        $this->get('/auth/ecosystem?token=not-a-token')->assertStatus(303)->assertRedirect('/login');
    }

    public function test_the_sign_in_post_is_not_rejected_for_a_missing_csrf_token_because_it_comes_from_another_origin(): void
    {
        // CSRF verification is skipped while unit tests run, which hid HTTP 419 in production on the Laravel 13
        // platforms: run the request in production mode, where the middleware is active.
        $this->app['env'] = 'production';
        $user = $this->user();
        $token = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->addMinute())->plainTextToken;

        $response = $this->post('/auth/ecosystem', ['token' => $token], ['Origin' => 'http://files.localhost']);

        $this->assertNotSame(419, $response->getStatusCode());
        $response->assertRedirect(route('solutions'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_platform_with_its_own_sign_in_opens_at_its_front_door_and_no_token_is_minted(): void
    {
        $user = $this->user();
        config(['ecosystem.platforms.mines.url' => 'https://mines.infodot.co.za', 'ecosystem.platforms.mines.active' => true]);

        $this->assertTrue(EcosystemHandoff::hasNoReceiver('mines'));
        $this->assertTrue(EcosystemHandoff::hasNoReceiver('charts'));
        $this->assertFalse(EcosystemHandoff::hasNoReceiver('files'));

        // The switcher goes straight there ...
        $this->assertSame('https://mines.infodot.co.za/', EcosystemHandoff::launchUrl($user, 'mines'));

        // ... and a hand-typed launch URL does the same, with no token and no form.
        $this->actingAs($user)->get('/go/mines')->assertRedirect('https://mines.infodot.co.za/');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        // A look-alike host is still refused.
        config(['ecosystem.platforms.mines.url' => 'https://mines.infodot.app']);
        $this->assertNull(EcosystemHandoff::launchUrl($user, 'mines'));
    }
}
