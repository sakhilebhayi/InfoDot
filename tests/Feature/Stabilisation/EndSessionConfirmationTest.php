<?php

namespace Tests\Feature\Stabilisation;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Oidc\OidcTestCase;

/**
 * Central sign-out ends the session on InfoDot and on every Dot platform, so it must be something
 * the signed-in person asked for. A platform proves that with the person's own id_token; anything
 * else (a bare link, a hint issued to somebody else) gets a confirmation page whose button posts.
 */
class EndSessionConfirmationTest extends OidcTestCase
{
    public function test_a_bare_link_does_not_sign_out_and_asks_first(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/oauth/end-session');

        $response->assertOk();
        $response->assertSee('Sign out of InfoDot and every Dot platform?');
        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_token"', false);
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    public function test_the_confirmation_button_signs_out(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->completeCodeFlow($user);

        $this->actingAs($user)->post('/oauth/end-session')->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('sso_platform_sessions', ['user_id' => $user->id]);
    }

    public function test_the_route_keeps_csrf_protection(): void
    {
        $route = app('router')->getRoutes()->getByName('openid.end_session_endpoint');

        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertFalse(
            (new \ReflectionMethod(VerifyCsrfToken::class, 'inExceptArray'))
                ->invoke(app(VerifyCsrfToken::class), Request::create('/oauth/end-session', 'POST')),
            'the sign-out post must not be exempt from the CSRF check',
        );
    }

    public function test_the_persons_own_id_token_signs_out_directly(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $result = $this->completeCodeFlow($user);

        $this->actingAs($user)
            ->get('/oauth/end-session?'.http_build_query(['id_token_hint' => $result['id_token']]))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_an_id_token_issued_to_someone_else_does_not_sign_out(): void
    {
        Http::fake();
        $other = User::factory()->create();
        $result = $this->completeCodeFlow($other);
        $this->post('/logout');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/oauth/end-session?'.http_build_query([
            'id_token_hint' => $result['id_token'],
            'post_logout_redirect_uri' => 'https://central.test/sso/logged-out',
            'state' => 'bye-123',
        ]));

        $response->assertOk();
        $response->assertSee('Sign out of InfoDot and every Dot platform?');
        $this->assertAuthenticatedAs($user);
    }

    public function test_confirming_keeps_the_registered_return_address_and_refuses_any_other(): void
    {
        Http::fake();
        $other = User::factory()->create();
        $hint = $this->completeCodeFlow($other)['id_token'];
        $this->post('/logout');

        $user = User::factory()->create();

        $page = $this->actingAs($user)->get('/oauth/end-session?'.http_build_query([
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://central.test/sso/logged-out',
            'state' => 'bye-123',
        ]));
        $page->assertSee('name="post_logout_redirect_uri" value="https://central.test/sso/logged-out"', false);
        $page->assertSee('name="state" value="bye-123"', false);

        $this->post('/oauth/end-session', [
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://central.test/sso/logged-out',
            'state' => 'bye-123',
        ])->assertRedirect('https://central.test/sso/logged-out?state=bye-123');
        $this->assertGuest();

        $this->actingAs($user)->post('/oauth/end-session', [
            'id_token_hint' => $hint,
            'post_logout_redirect_uri' => 'https://elsewhere.example/landing',
        ])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_signed_out_visitor_is_not_shown_the_question(): void
    {
        $this->get('/oauth/end-session')->assertRedirect('/');
    }
}
