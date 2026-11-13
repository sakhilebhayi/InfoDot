<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\Feature\Oidc\OidcTestCase;

/**
 * The Dot platforms sign people in through InfoDot's OpenID Connect provider. A suspended account must get nothing
 * from it: no authorisation, no tokens (first exchange or refresh), no profile, and the platforms it is already
 * signed in to are told to end their sessions.
 */
class AccountSuspensionOidcTest extends OidcTestCase
{
    private function suspendQuietly(User $user): void
    {
        // Only the mark, none of the clean-up: each door has to refuse on its own.
        $user->forceFill(['suspended_at' => now()])->save();
    }

    public function test_a_suspended_person_is_not_authorised_for_a_platform(): void
    {
        $user = $this->entitledUser();
        $this->suspendQuietly($user);
        [, $challenge] = $this->pkcePair();

        $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)));

        $response->assertRedirect('/login');
        $this->assertDatabaseCount('oauth_auth_codes', 0);
    }

    public function test_a_code_issued_before_the_suspension_buys_no_tokens(): void
    {
        $user = $this->entitledUser();
        [$verifier, $challenge] = $this->pkcePair();

        $redirect = $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)))
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $params);

        $this->suspendQuietly($user);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => 'https://central.test/sso/callback',
            'code' => $params['code'],
            'code_verifier' => $verifier,
        ])->assertStatus(400)->assertJsonMissingPath('access_token')->assertJsonMissingPath('id_token');

        $this->assertSame(0, Token::where('user_id', $user->id)->where('revoked', false)->count());
    }

    public function test_a_refresh_token_of_a_suspended_person_buys_no_new_tokens(): void
    {
        $user = $this->entitledUser();
        $result = $this->completeCodeFlow($user);
        $this->assertArrayHasKey('refresh_token', $result);

        $this->suspendQuietly($user);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $result['refresh_token'],
        ])->assertStatus(400)->assertJsonMissingPath('access_token');
    }

    public function test_a_refresh_token_still_works_for_everyone_else(): void
    {
        $result = $this->completeCodeFlow($this->entitledUser());

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->platform->oauth_client_id,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $result['refresh_token'],
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_userinfo_refuses_a_suspended_person(): void
    {
        $user = $this->entitledUser();
        $result = $this->completeCodeFlow($user);

        $this->suspendQuietly($user);
        $this->app['auth']->forgetGuards();

        $this->getJson('/oauth/userinfo', ['Authorization' => 'Bearer '.$result['access_token']])
            ->assertUnauthorized()
            ->assertJsonMissingPath('sub')
            ->assertJsonMissingPath('email');
    }

    public function test_suspending_revokes_provider_tokens_and_tells_the_signed_in_platforms(): void
    {
        Http::fake();

        $user = $this->entitledUser();
        $this->completeCodeFlow($user);

        $summary = app(AccountSuspension::class)->suspend($user, 'Reported', null);

        $this->assertCount(1, Http::recorded(fn ($request) => $request->url() === 'https://central.test/sso/backchannel-logout'));
        $this->assertSame(0, Token::where('user_id', $user->id)->where('revoked', false)->count());
        $this->assertSame(0, RefreshToken::whereIn('access_token_id', Token::where('user_id', $user->id)->pluck('id'))->where('revoked', false)->count());
        $this->assertDatabaseMissing('sso_platform_sessions', ['user_id' => $user->id]);
        $this->assertSame(1, $summary['provider_tokens']);
        $this->assertSame(1, $summary['platforms']);
    }
}
