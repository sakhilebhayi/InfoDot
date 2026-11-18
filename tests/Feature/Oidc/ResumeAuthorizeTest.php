<?php

namespace Tests\Feature\Oidc;

use App\Models\Team;
use App\Models\User;

/** R-54: "Switch on Dot.X" in the middle of an OIDC sign-in resumes that sign-in (and so the deep link), not /go. */
class ResumeAuthorizeTest extends OidcTestCase
{
    private function ownerWithoutPlatform(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user->fresh(), $team];
    }

    /** @return array<string, string> */
    private function query(array $overrides = []): array
    {
        [, $challenge] = $this->pkcePair();

        return $this->authorizeQuery($challenge, $overrides);
    }

    public function test_switching_the_platform_on_resumes_the_exact_sign_in_so_the_platform_keeps_its_state(): void
    {
        [$user, $team] = $this->ownerWithoutPlatform();
        $query = $this->query(['state' => 'deep-link-state-'.str_repeat('x', 30)]);

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($query))->assertForbidden();

        $resume = $this->post('/platforms/central/activate', ['company' => $team->uuid])->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('http://localhost/oauth/authorize?', $resume);
        parse_str((string) parse_url($resume, PHP_URL_QUERY), $back);
        $this->assertEqualsCanonicalizing($query, $back, 'the same request, nothing added, nothing dropped');

        // ...and following it now ends at the platform with its own state (which is where its deep link lives).
        $callback = $this->get($resume)->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://central.test/sso/callback?', $callback);
        parse_str((string) parse_url($callback, PHP_URL_QUERY), $params);
        $this->assertSame($query['state'], $params['state']);
        $this->assertNotEmpty($params['code']);
    }

    public function test_the_access_page_for_an_already_entitled_person_also_resumes(): void
    {
        [$user, $team] = $this->ownerWithoutPlatform();
        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($this->query()))->assertForbidden();
        $this->activatePlatforms($team, 'central');

        $this->get('/platforms/central/access')->assertRedirectContains('/oauth/authorize?');
    }

    public function test_without_a_parked_sign_in_the_old_behaviour_is_unchanged(): void
    {
        [$user, $team] = $this->ownerWithoutPlatform();

        $to = $this->actingAs($user)->post('/platforms/central/activate', ['company' => $team->uuid])->assertRedirect()->headers->get('Location');

        $this->assertStringNotContainsString('/oauth/authorize', $to);
    }

    public function test_a_parked_request_with_an_unregistered_redirect_is_never_resumed(): void
    {
        [$user, $team] = $this->ownerWithoutPlatform();
        $this->actingAs($user)->withSession(['sso.resume_authorize' => http_build_query($this->query(['redirect_uri' => 'https://evil.test/cb']))]);

        $to = $this->post('/platforms/central/activate', ['company' => $team->uuid])->assertRedirect()->headers->get('Location');

        $this->assertStringNotContainsString('evil.test', $to);
        $this->assertStringNotContainsString('/oauth/authorize', $to);
    }

    public function test_hostile_parked_values_are_dropped(): void
    {
        foreach ([
            ['client_id' => 'someone-else'],
            ['response_type' => 'token'],
            ['redirect_uri' => 'https://central.test/sso/callback/../evil'],
        ] as $override) {
            [$user, $team] = $this->ownerWithoutPlatform();
            $this->actingAs($user)->withSession(['sso.resume_authorize' => http_build_query($this->query($override))]);

            $to = $this->post('/platforms/central/activate', ['company' => $team->uuid])->assertRedirect()->headers->get('Location');
            $this->assertStringNotContainsString('/oauth/authorize', $to, json_encode($override));
        }
    }

    public function test_a_parked_request_for_another_platform_is_not_resumed_when_switching_this_one_on(): void
    {
        [$user, $team] = $this->ownerWithoutPlatform();
        $this->actingAs($user)->withSession(['sso.resume_authorize' => http_build_query($this->query())]);

        // The parked request belongs to Dot.Central; the person switches Dot.Design on instead.
        $to = $this->post('/platforms/design/activate', ['company' => $team->uuid])->assertRedirect()->headers->get('Location');

        $this->assertStringNotContainsString('/oauth/authorize', $to);
    }

    public function test_a_resumed_request_is_spent(): void
    {
        [$user, $team] = $this->ownerWithoutPlatform();
        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($this->query()))->assertForbidden();
        $this->post('/platforms/central/activate', ['company' => $team->uuid])->assertRedirectContains('/oauth/authorize');

        $this->get('/platforms/central/access')->assertRedirect()->assertRedirectContains('/go/');
    }
}
