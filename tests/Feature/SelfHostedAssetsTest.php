<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Support\OptionalVite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * R-49. The pages used to fetch their typefaces and the icon font from Google Fonts, the charting library from
 * jsDelivr, a stylesheet from cdnjs and the sign-in background from Unsplash, so a visit on a poor connection showed
 * fallback text, icons as words and an empty chart. All of it is now served from this site through the build. These
 * tests keep it so: no page names another host for a file, the policy no longer allows those hosts, and every font
 * the stylesheet declares is a local file.
 */
class SelfHostedAssetsTest extends TestCase
{
    use RefreshDatabase;

    private const FORMER_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'images.unsplash.com', 'source.unsplash.com'];

    private function signedIn(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    /** @return array<string, string> page name => HTML, one for every layout */
    private function pages(): array
    {
        $pages = [
            'landing' => $this->get('/')->assertOk()->getContent(),
            'about' => $this->get('/about')->assertOk()->getContent(),
            'sign in' => $this->get('/login')->assertOk()->getContent(),
            'register' => $this->get('/register')->assertOk()->getContent(),
            'forgot password' => $this->get('/forgot-password')->assertOk()->getContent(),
            'directory' => $this->get('/businesses')->assertOk()->getContent(),
            'not found' => $this->get('/no-such-page-'.uniqid())->assertNotFound()->getContent(),
        ];
        $this->actingAs($this->signedIn());
        foreach (['home' => '/home', 'analytics' => '/analytics', 'questions' => '/questions', 'platforms' => '/platforms'] as $name => $url) {
            $pages[$name] = $this->get($url)->assertOk()->getContent();
        }

        return array_map('strval', $pages);
    }

    public function test_no_layout_names_another_host_for_a_font_a_script_a_stylesheet_or_a_picture(): void
    {
        foreach ($this->pages() as $name => $html) {
            foreach (self::FORMER_HOSTS as $host) {
                $this->assertStringNotContainsString($host, $html, "the {$name} page still names {$host}");
            }
            preg_match_all('#<(?:script|img|iframe|source|video|audio)\b[^>]*\ssrc="(?:https?:)?//([^/"]+)|<link\b[^>]*\shref="(?:https?:)?//([^/"]+)[^>]*>|url\([\'"]?(?:https?:)?//([^/\'")]+)#i', $html, $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                // A canonical or alternate link names this site's own address and loads nothing.
                if (preg_match('/rel="(canonical|alternate)"/', $match[0])) {
                    continue;
                }
                $host = $match[1] ?: ($match[2] ?? '') ?: ($match[3] ?? '');
                $this->assertSame(parse_url((string) config('app.url'), PHP_URL_HOST), preg_replace('/:\d+$/', '', $host), "the {$name} page loads a file from {$host}: {$match[0]}");
            }
            $this->assertStringNotContainsString('rel="preconnect"', $html, "the {$name} page warms up a connection to another host");
        }
    }

    public function test_the_content_security_policy_allows_only_this_site_for_scripts_styles_fonts_and_pictures(): void
    {
        foreach (['/', '/login', '/businesses'] as $page) {
            $policy = (string) $this->get($page)->headers->get('Content-Security-Policy');
            $this->assertNotSame('', $policy, $page);

            foreach (self::FORMER_HOSTS as $host) {
                $this->assertStringNotContainsString($host, $policy, "{$page}: the policy still allows {$host}");
            }

            $directives = [];
            foreach (explode(';', $policy) as $part) {
                $words = preg_split('/\s+/', trim($part)) ?: [];
                $directives[(string) array_shift($words)] = $words;
            }
            $this->assertSame(["'self'", "'unsafe-inline'"], $directives['style-src'], $page);
            $this->assertSame(["'self'", 'data:'], $directives['font-src'], $page);
            $this->assertSame(["'self'", 'data:', 'blob:'], $directives['img-src'], $page);
            $this->assertCount(3, $directives['script-src'], $page);
            $this->assertSame(["'self'", "'unsafe-eval'"], array_values(array_filter($directives['script-src'], fn ($word) => ! str_starts_with($word, "'nonce-"))), "{$page}: scripts come from this site and by nonce only");
        }
    }

    public function test_every_font_the_stylesheet_declares_is_a_local_file_for_exactly_the_families_in_use(): void
    {
        $css = (string) file_get_contents(resource_path('css/fonts.css'));
        $package = json_decode((string) file_get_contents(base_path('package.json')), true);
        $installed = array_keys(($package['devDependencies'] ?? []) + ($package['dependencies'] ?? []));

        $this->assertSame(0, preg_match('#https?://|//fonts\.#', (string) preg_replace('#/\*.*?\*/#s', '', $css)), 'fonts.css names another host');

        preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $faces);
        $this->assertNotEmpty($faces[1]);
        $families = [];
        foreach ($faces[1] as $face) {
            preg_match("/font-family:\s*'([^']+)'/", $face, $family);
            preg_match('/font-weight:\s*([0-9 ]+);/', $face, $weight);
            preg_match("/src:\s*url\('([^']+)'\)\s*format\('woff2'\)/", $face, $src);
            preg_match('/font-display:\s*(\w+)/', $face, $display);
            $this->assertNotEmpty($src, 'a font face has no local woff2 source: '.$face);
            $families[$family[1]][trim($weight[1])] = true;

            $this->assertSame($family[1] === 'Material Symbols Rounded' ? 'block' : 'swap', $display[1] ?? null, $family[1].': text swaps in, icons block');

            if (str_starts_with($src[1], '../../node_modules/')) {
                $path = substr($src[1], strlen('../../node_modules/'));
                $name = implode('/', array_slice(explode('/', $path), 0, 2));
                $this->assertContains($name, $installed, $name.' is not installed by package.json');
                $this->assertMatchesRegularExpression('#^@fontsource(-variable)?/[a-z0-9-]+/files/[a-z0-9-]+\.woff2$#', $path);
            } else {
                $this->assertFileExists(resource_path('css/'.$src[1]), $src[1]);
            }
        }

        // Exactly what the pages set text in: nothing loaded that nothing uses (Inter, Manrope and Font Awesome are gone, and
        // since the sign-in and error pages joined The Yard so are DM Sans, Baloo 2 and Space Mono).
        ksort($families);
        $this->assertSame([
            'Archivo' => ['400', '500', '600', '700'],
            'Big Shoulders Stencil Display' => ['700', '800', '900'],
            'Material Symbols Rounded' => ['100 700'],
        ], array_map(fn (array $weights) => array_map('strval', array_keys($weights)), $families));

        // Each family is one the stylesheets or the layouts actually ask for.
        $users = (string) file_get_contents(resource_path('css/infodot.css')).file_get_contents(resource_path('views/layouts/guest.blade.php')).file_get_contents(resource_path('views/errors/_ecosystem-layout.blade.php'));
        foreach (array_keys($families) as $family) {
            $this->assertStringContainsString("'".$family."'", $users, $family.' is declared but nothing uses it');
        }
    }

    public function test_the_layouts_preload_the_fonts_of_the_first_screen_from_this_site(): void
    {
        $this->assertSame(['archivo', 'stencil', 'icons'], array_keys(OptionalVite::PRELOADABLE_FONTS));
        $css = (string) file_get_contents(resource_path('css/fonts.css'));
        foreach (OptionalVite::PRELOADABLE_FONTS as $path) {
            $this->assertStringContainsString(str_starts_with($path, 'node_modules/') ? "url('../../{$path}')" : "url('../".substr($path, strlen('resources/'))."')", $css, $path.' is preloaded but not a font the stylesheet uses');
        }

        foreach (['layouts/app.blade.php' => "['archivo', 'stencil', 'icons']", 'components/public-layout.blade.php' => "['archivo', 'stencil', 'icons']", 'layouts/guest.blade.php' => "['archivo', 'stencil', 'icons']"] as $layout => $fonts) {
            $this->assertStringContainsString("@include('partials.head-fonts', ['fonts' => {$fonts}])", (string) file_get_contents(resource_path('views/'.$layout)), $layout);
        }
        $partial = (string) file_get_contents(resource_path('views/partials/head-fonts.blade.php'));
        $this->assertStringContainsString('<link rel="preload" as="font" type="font/woff2" href="{{ $href }}" crossorigin>', $partial, 'a font preload without crossorigin is fetched twice');
    }

    public function test_the_chart_library_comes_from_the_build_and_only_on_the_page_that_draws_a_chart(): void
    {
        $app = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertSame(0, preg_match('/^import .*apexcharts/m', $app), 'app.js imports the chart library for every page');
        $this->assertStringContainsString("import('./analytics-chart')", $app);
        $this->assertStringContainsString("document.getElementById('activityChart')", $app);
        $this->assertStringContainsString("import ApexCharts from 'apexcharts'", (string) file_get_contents(resource_path('js/analytics-chart.js')));

        $view = (string) file_get_contents(resource_path('views/analytics/dashboard.blade.php'));
        $this->assertStringNotContainsString('<script', $view, 'the chart needs no script tag of its own');
        $this->assertStringContainsString('id="activityChart"', $view);
        foreach (['data-solutions', 'data-questions', 'data-months'] as $attribute) {
            $this->assertStringContainsString($attribute.'="{{', $view);
        }
    }

    public function test_the_sign_in_pages_are_drawn_from_the_build_with_no_photograph_behind_them(): void
    {
        // The way-in pages are The Yard (R-49): the shared shell, the product's two typefaces, and nothing fetched to decorate them.
        $card = (string) file_get_contents(resource_path('views/vendor/jetstream/components/authentication-card.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/layouts/guest.blade.php'));
        $this->assertStringNotContainsString('hero-1920.jpg', $card.$layout);
        $this->assertStringNotContainsString('<style', $card.$layout, 'the sign-in pages carry no styles of their own');
        $this->assertSame(0, preg_match('#https?://#', $card.$layout), 'the sign-in pages name another host');

        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('<body class="ix" data-area="people">', $html);
        $this->assertStringContainsString('class="ix-entry__card"', $html);
        $this->assertStringNotContainsString('background-image', $html);

        // The home page's photographs are still credited where they are used.
        $this->get('/about')->assertOk()
            ->assertSee('The photographs on the home page come from Wikimedia Commons')
            ->assertSee('by WAO Coworking')
            ->assertDontSee('sign-in pages');
    }

    public function test_a_fresh_build_serves_its_own_fonts(): void
    {
        // The test job does not build, and the committed build is older than this change, so this speaks only when the
        // build on disk is current. The same check runs against a real build in CI (tools/check-built-assets.mjs).
        $manifest = json_decode((string) @file_get_contents(public_path('build/manifest.json')), true) ?: [];
        $entry = $manifest['resources/css/app.css']['file'] ?? null;
        if (! isset($manifest['resources/fonts/material-symbols-rounded.woff2']) || $entry === null || ! is_file(public_path('build/'.$entry))) {
            $this->assertTrue(true);

            return;
        }

        $css = (string) file_get_contents(public_path('build/'.$entry));
        preg_match_all('#url\(([^)]+\.woff2)\)#', $css, $urls);
        // Archivo and the stencil face, each in Latin and Latin Extended, and the icons.
        $this->assertCount(5, array_unique($urls[1]));
        foreach (array_unique($urls[1]) as $url) {
            $this->assertStringStartsWith('/build/assets/', $url);
            $this->assertFileExists(public_path(ltrim($url, '/')));
        }
        $this->assertSame(0, preg_match('#url\(["\']?(https?:)?//#', $css), 'the built stylesheet names another host');
    }

    public function test_no_view_script_or_stylesheet_names_a_former_host(): void
    {
        $finder = (new Finder)->files()->in([resource_path('views'), resource_path('js'), resource_path('css')])->name(['*.php', '*.js', '*.css']);
        foreach ($finder as $file) {
            foreach (self::FORMER_HOSTS as $host) {
                $this->assertStringNotContainsString($host, $file->getContents(), $file->getRelativePathname().' names '.$host);
            }
        }
    }
}
