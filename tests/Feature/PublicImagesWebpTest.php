<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * R-75 phase 3: the two heaviest public photographs are offered as WebP first (about 60% lighter), with the JPEG kept as the
 * fallback, and the Features photo gives phones the 800px file. The shared host serves files slowly under load, so weight matters.
 * Vite is faked in tests (asset URLs come out empty), so this reads the templates, as BrandLogoTest does.
 */
class PublicImagesWebpTest extends TestCase
{
    private function source(string $view): string
    {
        return (string) file_get_contents(resource_path('views/'.$view));
    }

    public function test_the_homepage_hero_offers_the_skyline_as_webp_with_a_jpeg_fallback(): void
    {
        $html = $this->source('partials/home-hero.blade.php');

        $this->assertMatchesRegularExpression("#<source type=\"image/webp\" srcset=\"[^\"]*jhb-skyline-960\\.webp'\\) }} 960w, [^\"]*jhb-skyline-1920\\.webp'\\) }} 1920w\"#", $html);
        $this->assertStringContainsString("resources/img/pages/jhb-skyline-1920.jpg') }}\" srcset=", $html);
    }

    public function test_the_features_photo_is_responsive_and_webp_first(): void
    {
        $html = $this->source('features.blade.php');

        $this->assertMatchesRegularExpression("#<source type=\"image/webp\" srcset=\"[^\"]*jhb-city-800\\.webp'\\) }} 800w, [^\"]*jhb-city-1600\\.webp'\\) }} 1600w\"#", $html);
        $this->assertMatchesRegularExpression("#jhb-city-1600\\.jpg'\\) }}\" srcset=\"[^\"]*jhb-city-800\\.jpg'\\) }} 800w#", $html);
    }

    public function test_every_webp_the_templates_ask_for_exists_and_is_built(): void
    {
        foreach (['jhb-skyline-960', 'jhb-skyline-1920', 'jhb-city-800', 'jhb-city-1600'] as $name) {
            $this->assertFileExists(resource_path("img/pages/{$name}.webp"));
        }
        $this->assertStringContainsString("'../img/pages/*.webp'", (string) file_get_contents(resource_path('js/app.js')));
    }
}
