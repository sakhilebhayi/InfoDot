<?php

namespace Tests\Feature;

use App\Models\OnboardingEvent;
use App\Models\User;
use App\Services\Growth\OnboardingFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

/**
 * R-73: the homepage's two join buttons say where they are (?from=hero, ?from=close). The source is kept in the session and stored with
 * the "account created" step; anything else is ignored. The Growth Centre counts people by source and what they did next.
 */
class HomepageSignupSourceTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $email): User
    {
        $this->post('/register', [
            'name' => 'Sign Up', 'email' => $email, 'password' => 'green-kettle-river-9', 'password_confirmation' => 'green-kettle-river-9',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ])->assertRedirect();
        $user = User::where('email', $email)->sole();
        $this->post('/logout');

        return $user;
    }

    private function sourceOf(User $user): ?string
    {
        return OnboardingEvent::where('user_id', $user->id)->where('step', 'account_created')->sole()->meta['from'] ?? null;
    }

    public function test_the_homepage_join_buttons_carry_their_place(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('register', ['from' => 'hero']).'"', $html);
        $this->assertStringContainsString('href="'.route('register', ['from' => 'close']).'"', $html);
        // R-74: InfoDot's card on the Start tab has its own button and its own source, so the join call is still made only twice.
        $this->assertStringContainsString('href="'.route('register', ['from' => 'start']).'"', $html);
        $this->assertStringContainsString('Start with InfoDot', $html);
        $this->assertSame(2, substr_count(strip_tags($html), 'Join InfoDot for free'), 'the join call is made twice: at the top and at the close');
    }

    public function test_the_source_is_stored_with_the_new_account_and_anything_else_is_ignored(): void
    {
        $this->get('/register?from=hero')->assertOk();
        $hero = $this->signUp('hero@example.com');
        $this->assertSame('hero', $this->sourceOf($hero));

        $this->get('/register?from=close')->assertOk();
        $close = $this->signUp('close@example.com');
        $this->assertSame('close', $this->sourceOf($close));

        $this->get('/register?from=<script>')->assertOk();
        $odd = $this->signUp('odd@example.com');
        $this->assertNull($this->sourceOf($odd), 'an unknown value is not stored');

        $this->get('/register')->assertOk();
        $plain = $this->signUp('plain@example.com');
        $this->assertNull($this->sourceOf($plain), 'the session does not carry one person\'s source to the next');
    }

    public function test_the_growth_centre_counts_people_by_source_and_what_they_did_next(): void
    {
        $this->get('/register?from=hero')->assertOk();
        $hero = $this->signUp('hero2@example.com');
        OnboardingEvent::create(['user_id' => $hero->id, 'scope' => 0, 'step' => 'platform_opened', 'occurred_at' => now()]);
        $this->signUp('other@example.com');

        $rows = collect(app(OnboardingFunnel::class)->bySignupSource())->keyBy('source');

        $this->assertSame(1, $rows['hero']['accounts']);
        $this->assertSame(1, $rows['hero']['steps']['platform_opened']);
        $this->assertSame(0, $rows['close']['accounts']);
        $this->assertSame(1, $rows['other']['accounts']);
    }
}
