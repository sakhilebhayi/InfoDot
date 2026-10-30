<?php

namespace Tests\Feature\Hardening;

use App\Livewire\Comments;
use App\Models\User;
use App\Support\CanonicalUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** Regression tests for the security findings of the 2026-10-30 enterprise audit (docs/programme/enterprise/FINDINGS.md). */
class SecurityFixesTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function verified(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    // ---- S-01 handoff tokens ---------------------------------------------------------------------------------------------

    public function test_a_token_minted_for_a_satellite_cannot_sign_anyone_in_to_infodot(): void
    {
        $user = $this->verified();
        $token = $user->createToken('ecosystem-handoff:files', ['ecosystem:read'], now()->addMinute())->plainTextToken;

        $this->post('/auth/ecosystem', ['token' => $token], ['Origin' => 'http://files.localhost'])->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(1, DB::table('personal_access_tokens')->count(), 'the satellite token was not spent here');
    }

    public function test_a_valid_token_in_a_get_link_never_signs_anyone_in(): void
    {
        $user = $this->verified();
        $token = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->addMinute())->plainTextToken;

        $this->get('/auth/ecosystem?token='.$token)->assertStatus(303)->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_a_handoff_token_cannot_mint_another(): void
    {
        $user = $this->verified();
        $handoff = $user->createToken('ecosystem-handoff:files', ['ecosystem:read'], now()->addMinute());
        $this->withHeader('Authorization', 'Bearer '.$handoff->plainTextToken)->postJson('/api/ecosystem/token', ['platform' => 'files'])->assertForbidden();

    }

    public function test_an_ordinary_api_token_can_still_mint_a_handoff_token(): void
    {
        $normal = $this->verified()->createToken('mobile', ['*']);

        $this->withHeader('Authorization', 'Bearer '.$normal->plainTextToken)->postJson('/api/ecosystem/token', [])->assertOk()->assertJsonStructure(['token', 'expires_at']);
    }

    // ---- S-02 /api/user --------------------------------------------------------------------------------------------------

    public function test_the_api_user_response_never_carries_two_factor_secrets(): void
    {
        $user = $this->verified();
        $user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('["a"]')])->save();
        Sanctum::actingAs($user, ['*']);

        $json = $this->getJson('/api/user')->assertOk()->json();

        $this->assertArrayNotHasKey('two_factor_secret', $json);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $json);
        $this->assertArrayNotHasKey('password', $json);
    }

    // ---- S-03 sign-up and reset forms ------------------------------------------------------------------------------------

    public function test_the_forgot_password_form_is_limited_per_address(): void
    {
        $statuses = [];

        foreach (range(1, 8) as $i) {
            $statuses[] = $this->post('/forgot-password', ['email' => "nobody$i@example.test"])->getStatusCode();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 6), 'the first six are allowed');
        $this->assertSame(429, $statuses[7], 'the seventh and later are refused');
    }

    // ---- S-04 host header ------------------------------------------------------------------------------------------------

    public function test_generated_links_use_the_configured_url_not_the_request_host(): void
    {
        config(['app.url' => 'https://infodot.example']);
        CanonicalUrl::apply();

        try {
            $this->get('/login', ['Host' => 'evil.example']);
            $this->assertStringStartsWith('https://infodot.example/', route('password.reset', ['token' => 'x']));
            $this->assertStringStartsWith('https://infodot.example/', url('/anything'));
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    // ---- S-05 stripe webhook ---------------------------------------------------------------------------------------------

    public function test_the_stripe_webhook_route_does_not_exist_while_no_secret_is_set(): void
    {
        $this->assertEmpty(config('cashier.webhook.secret'));

        $this->postJson('/stripe/webhook', ['type' => 'customer.subscription.deleted'])->assertNotFound();
    }

    // ---- S-06 throttle buckets -------------------------------------------------------------------------------------------

    public function test_every_throttled_route_has_its_own_bucket(): void
    {
        $bad = [];

        foreach (app('router')->getRoutes() as $route) {
            if (str_contains($route->getActionName(), 'Laravel\\') || str_contains($route->getActionName(), 'Livewire\\')) {
                continue;   // routes the packages register are theirs
            }

            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && preg_match('/^throttle:\d+,\d+$/', $m)) {
                    $bad[] = $route->uri();
                }
            }
        }

        $this->assertSame([], $bad, 'an unprefixed throttle shares one counter with every other unprefixed throttle for the same person');
    }

    public function test_callbacks_have_their_own_limiters(): void
    {
        foreach (['billing-callbacks', 'livekit-webhook', 'ecosystem-redeem'] as $name) {
            $this->assertNotNull(RateLimiter::limiter($name), $name);
        }

        $this->assertSame('throttle:billing-callbacks', collect(app('router')->getRoutes()->getByName('api.billing.ticket-events')->gatherMiddleware())->first(fn ($m) => str_starts_with((string) $m, 'throttle:')));
        $this->assertSame('throttle:livekit-webhook', collect(app('router')->getRoutes()->getByName('api.seminars.livekit-webhook')->gatherMiddleware())->first(fn ($m) => str_starts_with((string) $m, 'throttle:')));
    }

    // ---- S-07 input size and flooding ------------------------------------------------------------------------------------

    public function test_oversized_text_is_refused_before_it_reaches_the_database(): void
    {
        $user = $this->verified();
        $user->forceFill(['current_team_id' => $user->personalTeam()?->id])->save();

        $this->actingAs($user)->post('/questions/add', ['topics' => [1], 'question' => 'Fine title', 'description' => str_repeat('x', 20001)])->assertSessionHasErrors('description');
        $this->actingAs($user)->post('/solution/add', ['solution_title' => 'Fine', 'solution_description' => str_repeat('x', 20001), 'tags-input' => 'a,b', 'duration' => 1, 'duration_type' => 'days', 'solution_heading' => ['h'], 'solution_body' => ['b']])->assertSessionHasErrors('solution_description');
        $this->actingAs($user)->post('/solution/add', ['solution_title' => 'Fine', 'solution_description' => 'ok ok', 'tags-input' => 'a,b', 'duration' => 1, 'duration_type' => 'days', 'solution_heading' => ['h'], 'solution_body' => [str_repeat('x', 5001)]])->assertSessionHasErrors('solution_body.0');
    }

    public function test_a_person_cannot_flood_a_thread_with_comments(): void
    {
        $asker = $this->person('Asker');
        $reader = $this->person('Reader');
        $question = $this->question($asker, 'public', 'How do I register a clothing brand?');
        $component = Livewire::actingAs($reader)->test(Comments::class, ['model' => $question, 'question' => $question]);

        foreach (range(1, 20) as $i) {
            $component->set('newCommentState.body', "Comment $i")->call('postComment')->assertHasNoErrors();
        }

        $component->set('newCommentState.body', 'One too many')->call('postComment')->assertHasErrors('newCommentState.body');
        $component->set('newCommentState.body', str_repeat('x', 5001))->call('postComment')->assertHasErrors('newCommentState.body');
    }
}
