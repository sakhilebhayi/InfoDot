<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-71: the cookie banner. InfoDot's one optional cookie (dot_src) is only set after the visitor accepts it; until then a tagged arrival
 * waits in the visit's session, Decline discards it and removes any dot_src, and the choice is remembered in a strictly necessary cookie.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    private function src(): string
    {
        return (string) config('acquisition.cookie');
    }

    private function consent(): string
    {
        return (string) config('acquisition.consent_cookie');
    }

    public function test_a_signed_out_visitor_who_has_not_chosen_sees_the_banner_on_public_and_sign_in_pages(): void
    {
        foreach (['/', '/about', '/business-tools', '/login', '/register'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('data-cookie-banner', $html, $path);
            $this->assertStringContainsString('name="choice" value="accepted"', $html, $path);
            $this->assertStringContainsString('name="choice" value="declined"', $html, $path);
            $this->assertStringContainsString(route('cookies'), $html, $path);
        }
    }

    public function test_the_banner_is_not_shown_once_chosen_or_to_a_signed_in_person(): void
    {
        $this->withCookie($this->consent(), 'declined')->get('/')->assertOk()->assertDontSee('data-cookie-banner', false);
        $this->withCookie($this->consent(), 'accepted')->get('/about')->assertOk()->assertDontSee('data-cookie-banner', false);

        $this->withCookie($this->consent(), 'maybe')->get('/')->assertOk()->assertSee('data-cookie-banner', false);

        $this->actingAs(User::factory()->create())->get('/about')->assertOk()->assertDontSee('data-cookie-banner', false);
    }

    public function test_before_a_choice_a_tagged_arrival_sets_no_optional_cookie_and_waits_in_the_session(): void
    {
        $this->get('/?ref=KHANYI-7&utm_campaign=launch')->assertOk()->assertCookieMissing($this->src())
            ->assertSessionHas((string) config('acquisition.pending_key'), fn ($a) => $a['r'] === 'KHANYI-7' && $a['u']['campaign'] === 'launch');
    }

    public function test_accepting_keeps_the_arrival_that_was_waiting(): void
    {
        $this->get('/?ref=KHANYI-7')->assertOk();

        $this->from('/about')->post(route('cookie-consent'), ['choice' => 'accepted'])
            ->assertRedirect('/about')
            ->assertCookie($this->consent(), 'accepted')
            ->assertCookie($this->src())
            ->assertSessionMissing((string) config('acquisition.pending_key'));
    }

    public function test_declining_discards_the_arrival_and_removes_an_existing_optional_cookie(): void
    {
        $this->get('/?ref=KHANYI-7')->assertOk();

        $response = $this->post(route('cookie-consent'), ['choice' => 'declined'])
            ->assertCookie($this->consent(), 'declined')
            ->assertSessionMissing((string) config('acquisition.pending_key'));

        $forgotten = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === $this->src());
        $this->assertNotNull($forgotten, 'Decline sends an expired dot_src');
        $this->assertTrue($forgotten->isCleared());
    }

    public function test_after_a_choice_arrivals_follow_it(): void
    {
        $this->withCookie($this->consent(), 'declined')->get('/?ref=KHANYI-7')->assertOk()->assertCookieMissing($this->src())
            ->assertSessionMissing((string) config('acquisition.pending_key'));

        $this->withCookie($this->consent(), 'accepted')->get('/?ref=ABCD-1234')->assertOk()->assertCookie($this->src());
    }

    public function test_the_banner_answer_saves_without_a_reload_and_rejects_anything_else(): void
    {
        $this->postJson(route('cookie-consent'), ['choice' => 'accepted'])->assertNoContent()->assertCookie($this->consent(), 'accepted');
        $this->postJson(route('cookie-consent'), ['choice' => 'everything'])->assertUnprocessable();
        $this->postJson(route('cookie-consent'), [])->assertUnprocessable();
    }

    public function test_the_cookie_policy_lists_the_consent_cookie_and_lets_you_change_your_choice(): void
    {
        $html = $this->withCookie($this->consent(), 'accepted')->get(route('cookies'))->assertOk()->getContent();

        $this->assertStringContainsString('dot_consent', $html);
        $this->assertStringContainsString('only ever set if you allow it in the cookie banner', $html);
        $this->assertStringContainsString('you have allowed the optional', $html);
        $this->assertMatchesRegularExpression('/value="accepted"[^>]*disabled/', $html);
        $this->assertStringContainsString('Decline the optional cookie', $html);
    }
}
