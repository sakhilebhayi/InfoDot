<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\Feature\Stabilisation\Support\IconFont;
use Tests\TestCase;

/**
 * The site ships exactly one icon font: Material Symbols ROUNDED, served from this
 * site since R-49 and cut down to the icons in use (resources/fonts, built by
 * tools/build-icon-font.py from the list beside it). The apps menu, the platforms widget and the analytics page asked for
 * `material-symbols-outlined`, a font nothing loads, so on 2026-10-17 every one of
 * their icons showed as its ligature NAME ("home", "smart_toy", "credit_card"...),
 * and the words made the apps menu grow far off the screen.
 */
class IconFontTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_view_asks_for_an_icon_font_the_layout_does_not_load(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_contains($file->getPathname(), '/vendor/')) {
                continue;
            }

            preg_match_all('/material-symbols-([a-z]+)/', $file->getContents(), $m);
            foreach (array_unique($m[1]) as $family) {
                if ($family !== 'rounded') {
                    $offenders[] = $file->getRelativePathname().' uses material-symbols-'.$family;
                }
            }
        }

        $this->assertSame([], $offenders, 'only the Rounded family is loaded by the app layout');
    }

    public function test_the_rounded_icon_font_is_served_from_this_site_and_never_shows_an_icon_as_its_name(): void
    {
        $fonts = (string) file_get_contents(resource_path('css/fonts.css'));

        $this->assertSame(1, preg_match("/@font-face\\s*\\{[^}]*font-family:\\s*'Material Symbols Rounded'[^}]*\\}/", $fonts, $face), 'the icon font is not declared');
        $this->assertStringContainsString("url('../fonts/material-symbols-rounded.woff2')", $face[0]);
        $this->assertStringContainsString('font-display: block', $face[0], 'an icon is typed as its name: the name must stay hidden while the font loads');
        $this->assertStringContainsString("@import './fonts.css';", (string) file_get_contents(resource_path('css/app.css')));

        foreach (['layouts/app.blade.php', 'layouts/guest.blade.php', 'components/public-layout.blade.php', 'errors/_ecosystem-layout.blade.php'] as $layout) {
            $source = (string) file_get_contents(resource_path('views/'.$layout));
            $this->assertStringNotContainsString('fonts.googleapis.com', $source, $layout);
            $this->assertStringContainsString('resources/css/app.css', $source, $layout.' does not load the stylesheet that declares the icon font');
        }
    }

    public function test_the_committed_icon_font_is_the_one_built_from_the_committed_list(): void
    {
        $record = json_decode((string) file_get_contents(resource_path('fonts/material-symbols-rounded.json')), true, flags: JSON_THROW_ON_ERROR);
        $font = (string) file_get_contents(resource_path('fonts/material-symbols-rounded.woff2'));

        $this->assertSame('wOF2', substr($font, 0, 4), 'not a WOFF2 file');
        $this->assertSame($record['sha256'], hash('sha256', $font), 'the font was changed without tools/build-icon-font.py, or its record was');
        $this->assertSame($record['icons'], IconFont::names(), 'the list of icons changed but the font was not rebuilt: run tools/build-icon-font.py');
        $this->assertSame(['FILL', 'GRAD', 'opsz', 'wght'], $record['axes'], 'the font kept every axis the stylesheet sets');
        $this->assertLessThan(200_000, strlen($font), 'the subset has grown past 200 kB: is the full font back?');
    }

    public function test_every_icon_a_view_or_the_code_names_is_in_the_font(): void
    {
        $asked = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = $file->getContents();
            // The element's own text, and every quoted name inside it (a Blade or Alpine expression choosing between icons).
            preg_match_all('/<(?:span|i)\b(?:[^>]|(?<=[-=])>)*material-symbols-rounded(?:[^>]|(?<=[-=])>)*>(.*?)<\/(?:span|i)>/s', $source, $m, PREG_SET_ORDER);
            foreach ($m as [$element, $inner]) {
                if (preg_match('/^[a-z0-9_]+$/', trim($inner))) {
                    $asked[trim($inner)][] = $file->getRelativePathname();
                }
                foreach (IconFont::namesIn($element) as $name) {
                    $asked[$name][] = $file->getRelativePathname();
                }
            }
            preg_match_all('/\bicon="([a-z0-9_]+)"/', $source, $attribute);
            foreach ($attribute[1] as $name) {
                $asked[$name][] = $file->getRelativePathname();
            }
        }

        // Icons chosen in code: 'icon' => '…', $icon = '…', and the values of constants named …ICONS.
        foreach (array_merge(File::allFiles(app_path()), File::allFiles(config_path())) as $file) {
            $source = $file->getContents();
            preg_match_all("/['\"]icon['\"]\\s*=>\\s*'([a-z0-9_]+)'|\\\$icon\\s*=\\s*'([a-z0-9_]+)'/", $source, $m);
            $names = array_filter(array_merge($m[1], $m[2]));
            preg_match_all('/const\s+\w*ICONS\s*=\s*\[(.*?)\];/s', $source, $constants);
            foreach ($constants[1] as $body) {
                preg_match_all("/=>\\s*'([a-z0-9_]+)'/", $body, $values);
                $names = array_merge($names, $values[1]);
            }
            foreach ($names as $name) {
                $asked[$name][] = $file->getRelativePathname();
            }
        }

        $this->assertGreaterThan(80, count($asked));
        foreach ($asked as $name => $files) {
            $this->assertContains($name, IconFont::names(), "The icon \"{$name}\" (".implode(', ', array_unique($files)).') is not in the icon font, so it would show as the word. Add it to resources/fonts/material-symbols-rounded.txt and run tools/build-icon-font.py.');
        }
    }

    public function test_signed_in_pages_that_show_icons_render_only_the_loaded_family(): void
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        foreach (['/questions', '/platforms', '/analytics', '/solutions'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('material-symbols-outlined', $html, $url);
            $this->assertStringContainsString('material-symbols-rounded', $html, $url);
            $this->assertSame([], IconFont::notInTheFont((string) $html), $url.' shows an icon the font does not hold');
        }
    }
}
