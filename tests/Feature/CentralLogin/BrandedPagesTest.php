<?php

namespace Tests\Feature\CentralLogin;

use App\Models\DotPlatform;
use App\Models\PlatformBranding;
use Laravel\Passport\ClientRepository;

/** R-54 1b: the branded way-in pages: one set of parts, a platform's identity beside the form, "Powered by" only when a platform started it. */
class BrandedPagesTest extends CentralLoginTestCase
{
    private const POWERED = 'Powered by';

    public function test_a_direct_visit_is_the_standard_page_with_no_powered_by_and_no_brand_panel(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(self::POWERED, $html, $path);
            $this->assertStringNotContainsString('ix-auth__brand', $html, $path);
            $this->assertStringNotContainsString('noindex', $html, $path);
            $this->assertStringContainsString('ix-entry__card', $html, $path);
        }
    }

    public function test_a_platform_sign_in_shows_the_platform_headline_the_brand_panel_and_powered_by(): void
    {
        $this->get($this->authorizeUrl());

        $this->get('/login')->assertOk()
            ->assertSee('Welcome back to Dot.Central', false)
            ->assertSee('ix-auth__brand', false)
            ->assertSee('The control room for a mining shift')
            ->assertSee(self::POWERED)
            ->assertSee('class="ix-powered"', false)
            ->assertSee('noindex, nofollow', false)
            ->assertSee('--brand-primary:', false);

        $this->get('/register')->assertOk()
            ->assertSee('Get started with Dot.Central', false)
            ->assertSee(self::POWERED);
    }

    public function test_the_recovery_and_verification_pages_carry_the_brand_and_powered_by_too(): void
    {
        $this->get($this->authorizeUrl());

        foreach (['/forgot-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('ix-auth__brand', false)->assertSee(self::POWERED);
        }

        $this->actingAs($this->newcomer(verified: false))->get('/email/verify')->assertOk()->assertSee('you will carry on to Dot.Central')->assertSee(self::POWERED);
    }

    public function test_the_page_has_exactly_one_h1_and_the_form_is_in_the_main_landmark(): void
    {
        $this->get($this->authorizeUrl());
        $html = $this->get('/login')->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('#<main id="main" class="ix-auth__main">.*<form.*</form>#s', $html);
    }

    public function test_a_spoofed_parameter_does_not_brand_the_page_or_show_powered_by(): void
    {
        $html = $this->get('/login?platform=central&client='.$this->platform->oauth_client_id)->getContent();

        $this->assertStringNotContainsString(self::POWERED, $html);
        $this->assertStringNotContainsString('Welcome back to Dot.Central', $html);
    }

    public function test_the_branding_row_drives_the_words_colours_and_buttons(): void
    {
        PlatformBranding::create([
            'dot_platform_id' => $this->platform->id, 'display_name' => 'Central', 'headline_login' => 'Back on shift', 'headline_register' => 'Join the shift',
            'primary_color' => '#f2a70b', 'mode' => 'dark', 'cta_login' => 'Start shift', 'cta_register' => 'Join now', 'promo_text' => 'Shift handover, in one place.', 'active' => true, 'version' => 2,
        ]);
        $this->get($this->authorizeUrl());

        $this->get('/login')->assertSee('Back on shift')->assertSee('Start shift')->assertSee('--brand-primary:#f2a70b', false)->assertSee('data-mode="dark"', false)->assertSee('Shift handover, in one place.');
        $this->get('/register')->assertSee('Join the shift')->assertSee('Join now');
    }

    public function test_the_bundled_imagery_is_served_from_our_own_origin_and_nothing_else_can_be_named(): void
    {
        config(['ecosystem.platforms.mines' => config('ecosystem.platforms.mines')]);

        $this->get('/brand/platform/mines/hero-960.webp')->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/brand/platform/mines/logo-on-dark.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/brand/platform/mines/hero-960.webp.php')->assertNotFound();
        $this->get('/brand/platform/mines/..%2f..%2f.env')->assertNotFound();
        $this->get('/brand/platform/mines/hero-777.webp')->assertNotFound();
        $this->get('/brand/platform/not-a-platform/hero-960.webp')->assertNotFound();
        $this->get('/brand/platform/tasks/logo-on-dark.png')->assertNotFound(); // light-mode platforms ship no dark logo
    }

    public function test_a_page_for_a_platform_with_bundled_imagery_uses_a_responsive_lazy_hero_with_fixed_dimensions(): void
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Dot.Mines', ['https://mines.test/sso/callback'], confidential: true);
        DotPlatform::create(['name' => 'Dot.Mines', 'slug' => 'mines', 'domain' => 'https://mines.test', 'oauth_client_id' => $client->getKey(), 'redirect_uris' => ['https://mines.test/sso/callback'], 'scopes' => ['openid'], 'status' => 'active']);
        $this->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($this->pkcePair()[1], ['client_id' => $client->getKey(), 'redirect_uri' => 'https://mines.test/sso/callback'])));

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('hero-480.webp 480w', $html);
        $this->assertStringContainsString('hero-1600.webp 1600w', $html);
        $this->assertMatchesRegularExpression('#<img class="ix-auth__hero"[^>]*width="1600"[^>]*height="1000"[^>]*loading="lazy"#s', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('logo-on-dark.png', $html, 'a dark platform shows its original dark-ground logo');
        $this->assertStringNotContainsString('https://', str_replace(['https://mines.test', 'http://localhost'], '', preg_replace('#https://(www\.)?w3\.org[^"\']*#', '', $html)), 'no image or script from another host');
    }

    public function test_every_bundled_image_matches_the_platform_that_ships_it(): void
    {
        foreach (array_keys(config('platform_branding')) as $slug) {
            foreach ([480, 960, 1600] as $w) {
                $this->assertFileExists(resource_path("brand/platforms/{$slug}/hero-{$w}.webp"));
            }
        }
    }

    public function test_forms_keep_their_loading_state_and_the_registration_page_its_strength_advice(): void
    {
        $this->get('/login')->assertSee('x-data="{ sending: false }"', false)->assertSee('Signing in…', false)->assertSee('aria-busy', false);
        $this->get('/register')->assertSee('ix-strength', false)->assertSee('type="checkbox"', false)->assertSee('Terms of Service');
    }
}
