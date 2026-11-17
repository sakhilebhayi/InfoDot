<?php

namespace App\Support\Branding;

use App\Models\DotPlatform;
use App\Models\PlatformBranding;
use App\Models\PlatformCatalogue;
use Dot\Ui\PlatformLogos;
use Illuminate\Support\Facades\Schema;

/**
 * R-54: the ONLY thing a sign-in or registration page may know about the platform that sent the person. A plain,
 * allowlisted value object: a name, words, colours, image addresses and a version. It has no property for the client
 * id, a secret, a redirect URI or a callback, so a template cannot print one by mistake, and every colour is
 * re-checked as `#rrggbb` here so a bad stored value can never reach a style rule.
 *
 * Built from the platform's branding row when there is one, and from the catalogue and registry (name, tagline,
 * accent) when there is not, so a registered platform is never shown without a name.
 */
final class PlatformBrandingView
{
    /** Image address pattern: same-origin, our own route only. */
    public const IMAGE_ROUTE = 'branding.image';

    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $headlineLogin,
        public readonly string $headlineRegister,
        public readonly ?string $description,
        public readonly ?string $promo,
        public readonly string $ctaLogin,
        public readonly string $ctaRegister,
        public readonly string $primary,
        public readonly string $secondary,
        public readonly string $accent,
        public readonly string $onPrimary,
        public readonly string $mode,
        public readonly ?string $logoUrl,
        public readonly ?string $heroUrl,
        public readonly ?string $heroAlt,
        public readonly ?string $backgroundUrl,
        public readonly int $version,
    ) {}

    public static function forPlatform(DotPlatform $platform): self
    {
        $row = Schema::hasTable('platform_branding') ? $platform->branding : null;

        // A branding row an administrator switched off (or that does not exist) falls back to the registry and catalogue.
        $row = $row instanceof PlatformBranding && $row->active ? $row : null;

        return self::build($platform, $row);
    }

    /** The same view built from an unsaved row, for the admin preview. */
    public static function preview(DotPlatform $platform, PlatformBranding $row): self
    {
        return self::build($platform, $row);
    }

    private static function build(DotPlatform $platform, ?PlatformBranding $row): self
    {
        $slug = (string) $platform->slug;
        $registry = (array) config('ecosystem.platforms.'.$slug, []);
        $catalogue = PlatformCatalogue::query()->where('key', $slug)->first();

        $name = self::text($row?->display_name) ?? self::text($catalogue?->name) ?? self::text($registry['name'] ?? null) ?? self::text($platform->name) ?? ucfirst($slug);

        // The registry accent is the fallback for the one colour a platform always has.
        $primary = self::hex($row?->primary_color) ?? self::hex($registry['accent'] ?? null) ?? '#2f8fd6';
        $accent = self::hex($row?->accent_color) ?? $primary;
        $secondary = self::hex($row?->secondary_color) ?? $primary;
        $mode = in_array($row?->mode, PlatformBranding::MODES, true) ? (string) $row->mode : 'light';

        return new self(
            slug: $slug,
            name: $name,
            headlineLogin: self::text($row?->headline_login) ?? 'Welcome back to '.$name,
            headlineRegister: self::text($row?->headline_register) ?? 'Get started with '.$name,
            description: self::text($row?->description) ?? self::text($catalogue?->tagline),
            promo: self::text($row?->promo_text),
            ctaLogin: self::text($row?->cta_login) ?? 'Sign in',
            ctaRegister: self::text($row?->cta_register) ?? 'Create account',
            primary: strtolower($primary),
            secondary: strtolower($secondary),
            accent: strtolower($accent),
            onPrimary: self::readableOn($primary),
            mode: $mode,
            logoUrl: self::image($row, 'logo_path', 'logo') ?? self::fallbackLogo($slug),
            heroUrl: self::image($row, 'hero_path', 'hero'),
            heroAlt: self::text($row?->hero_alt),
            backgroundUrl: self::image($row, 'background_path', 'background'),
            version: $row instanceof PlatformBranding ? (int) $row->version : 0,
        );
    }

    /** The custom properties a page sets on its root element. Every value was validated above, so the string is safe. */
    public function cssVariables(): string
    {
        return '--brand-primary:'.$this->primary.';--brand-secondary:'.$this->secondary.';--brand-accent:'.$this->accent.';--brand-on-primary:'.$this->onPrimary.';';
    }

    /** @return array<string, string|int|null> the allowlist, in one place, for tests and the admin preview */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    private static function hex(mixed $value): ?string
    {
        return is_string($value) && preg_match(PlatformBranding::HEX, $value) === 1 ? $value : null;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : $value;
    }

    /** Black or white, whichever reads better on the colour (WCAG relative luminance). */
    public static function readableOn(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(function (string $part): float {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $luminance > 0.179 ? '#111111' : '#ffffff';
    }

    private static function image(?PlatformBranding $row, string $column, string $kind): ?string
    {
        if ($row === null) {
            return null;
        }

        $path = $row->{$column};

        if (! is_string($path) || $path === '' || ! $row->platform) {
            return null;
        }

        return route(self::IMAGE_ROUTE, ['slug' => $row->platform->slug, 'kind' => $kind, 'v' => $row->version]);
    }

    private static function fallbackLogo(string $slug): ?string
    {
        $file = PlatformLogos::file($slug);

        return $file !== null ? asset('dot-ui/img/platforms/'.$file) : null;
    }
}
