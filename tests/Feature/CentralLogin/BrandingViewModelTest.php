<?php

namespace Tests\Feature\CentralLogin;

use App\Models\PlatformBranding;
use App\Support\Branding\PlatformBrandingView;

/** R-54: what a template may know about a platform. Allowlisted, safe to print, never a secret. */
class BrandingViewModelTest extends CentralLoginTestCase
{
    public function test_without_a_row_the_view_falls_back_to_the_catalogue_and_the_registry(): void
    {
        $view = PlatformBrandingView::forPlatform($this->platform);

        $this->assertSame('Dot.Central', $view->name);
        $this->assertSame('Welcome back to Dot.Central', $view->headlineLogin);
        $this->assertSame('Get started with Dot.Central', $view->headlineRegister);
        $this->assertSame('The control room for a mining shift', $view->description);
        $this->assertSame('#f2a70b', $view->primary, 'the recorded identity of Dot.Central (config/platform_branding.php)');
        $this->assertSame('dark', $view->mode);
        $this->assertSame(0, $view->version);
    }

    public function test_a_platform_with_no_recorded_identity_uses_the_registry_accent(): void
    {
        config(['platform_branding.central' => null]);

        $view = PlatformBrandingView::forPlatform($this->platform->fresh());

        $this->assertSame(strtolower(config('ecosystem.platforms.central.accent')), $view->primary);
        $this->assertSame('light', $view->mode);
    }

    public function test_a_row_overrides_the_fallback(): void
    {
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'display_name' => 'Central', 'primary_color' => '#112233', 'mode' => 'dark', 'headline_login' => 'Back on shift', 'version' => 3, 'active' => true]);

        $view = PlatformBrandingView::forPlatform($this->platform->fresh());

        $this->assertSame('Central', $view->name);
        $this->assertSame('Back on shift', $view->headlineLogin);
        $this->assertSame('#112233', $view->primary);
        $this->assertSame('dark', $view->mode);
        $this->assertSame(3, $view->version);
    }

    public function test_a_switched_off_row_is_ignored(): void
    {
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'display_name' => 'Hidden', 'primary_color' => '#112233', 'active' => false]);

        $this->assertSame('Dot.Central', PlatformBrandingView::forPlatform($this->platform->fresh())->name);
    }

    public function test_the_view_exposes_only_the_allowlisted_fields_and_no_secret_or_internal(): void
    {
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'primary_color' => '#112233', 'approved_redirect_domains' => ['central.test']]);
        $array = PlatformBrandingView::forPlatform($this->platform->fresh())->toArray();

        $this->assertEqualsCanonicalizing(
            ['slug', 'name', 'headlineLogin', 'headlineRegister', 'description', 'promo', 'ctaLogin', 'ctaRegister', 'primary', 'secondary', 'accent', 'onPrimary', 'mode', 'logoUrl', 'heroUrl', 'heroAlt', 'backgroundUrl', 'version', 'logoDarkUrl', 'heroSet'],
            array_keys($array),
        );

        $dump = json_encode($array);
        foreach ([$this->clientSecret, (string) $this->platform->oauth_client_id, 'https://central.test/sso/callback', 'backchannel', 'redirect_uris', 'ECOSYSTEM_SECRET'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump, 'must not leak: '.$secret);
        }
    }

    public function test_a_login_page_never_prints_the_client_id_secret_or_redirect_uri(): void
    {
        $this->get($this->authorizeUrl());
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString($this->clientSecret, $html);
        $this->assertStringNotContainsString('https://central.test/sso/callback', $html);
    }

    public function test_a_bad_stored_colour_never_reaches_a_style_rule(): void
    {
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'primary_color' => 'red;}</style><script>', 'secondary_color' => 'javascript:1', 'accent_color' => '#12']);
        $view = PlatformBrandingView::forPlatform($this->platform->fresh());

        $this->assertMatchesRegularExpression('/\A--brand-primary:#[0-9a-f]{6};--brand-secondary:#[0-9a-f]{6};--brand-accent:#[0-9a-f]{6};--brand-on-primary:#[0-9a-f]{6};--brand-action:#[0-9a-f]{6};--brand-on-action:#[0-9a-f]{6};--brand-action-dk:#[0-9a-f]{6};--brand-on-action-dk:#[0-9a-f]{6};\z/', $view->cssVariables());
    }

    public function test_the_action_colour_never_sinks_into_the_form_ground(): void
    {
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'primary_color' => '#14181f', 'accent_color' => '#5a6371']);
        $dark = PlatformBrandingView::forPlatform($this->platform->fresh());
        $this->assertSame('#14181f', $dark->actionColour(false));
        $this->assertSame('#e9ecec', $dark->actionColour(true), 'near-black on the dark page falls back to light ink when the accent is also dark');

        PlatformBranding::query()->update(['primary_color' => '#f8f8f0']);
        $this->assertSame('#1b2124', PlatformBrandingView::forPlatform($this->platform->fresh())->actionColour(false));

        PlatformBranding::query()->update(['primary_color' => '#0b0e12', 'accent_color' => '#f2a70b']);
        $this->assertSame('#f2a70b', PlatformBrandingView::forPlatform($this->platform->fresh())->actionColour(true));
    }

    public function test_html_in_words_is_stripped(): void
    {
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'headline_login' => '<script>alert(1)</script>Hello', 'description' => '<b>Bold</b> claim']);
        $view = PlatformBrandingView::forPlatform($this->platform->fresh());

        $this->assertStringNotContainsString('<', $view->headlineLogin.$view->description);
    }

    public function test_readable_text_colour_follows_contrast(): void
    {
        $this->assertSame('#111111', PlatformBrandingView::readableOn('#f1c62e'));
        $this->assertSame('#ffffff', PlatformBrandingView::readableOn('#14181c'));
        $this->assertSame('#ffffff', PlatformBrandingView::readableOn('#2338d8'));
    }
}
