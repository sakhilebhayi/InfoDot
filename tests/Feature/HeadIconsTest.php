<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * R-49. public/favicon.ico was an empty file from the first commit, so no page had a tab icon, and the sign-in pages
 * named an older mark. There is now one set of icons, cut from the logo, named by one partial that every layout
 * includes. These tests keep the files real, the layouts in agreement, and the deploy copying them to the web root.
 */
class HeadIconsTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT_FILES = ['favicon.ico', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png'];

    /** @return list<string> the icon, manifest and theme-colour tags of a page, in order */
    private function iconTags(string $html): array
    {
        preg_match_all('/<link\b[^>]*\brel="(?:shortcut icon|icon|apple-touch-icon|manifest|mask-icon)"[^>]*>|<meta name="theme-color"[^>]*>/', $html, $m);

        return $m[0];
    }

    public function test_the_icon_files_are_real_images_of_the_stated_sizes(): void
    {
        foreach (['apple-touch-icon.png' => 180, 'icon-192.png' => 192, 'icon-512.png' => 512] as $file => $side) {
            $info = getimagesize(public_path($file));
            $this->assertNotFalse($info, $file.' is not an image');
            $this->assertSame([$side, $side, IMAGETYPE_PNG], [$info[0], $info[1], $info[2]], $file);
        }

        // An .ico file: a 6-byte header (reserved 0, type 1, count), then one 16-byte entry per picture.
        $ico = (string) file_get_contents(public_path('favicon.ico'));
        $this->assertGreaterThan(1000, strlen($ico), 'favicon.ico is empty or a stub');
        $header = unpack('vreserved/vtype/vcount', $ico);
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], $header);
        $sizes = [];
        for ($i = 0; $i < 3; $i++) {
            $entry = unpack('Cwidth/Cheight/Ccolours/Creserved/vplanes/vbits/Vbytes/Voffset', $ico, 6 + 16 * $i);
            $sizes[] = $entry['width'];
            $picture = substr($ico, $entry['offset'], $entry['bytes']);
            $this->assertSame($entry['bytes'], strlen($picture), 'an icon entry points outside the file');
            $inner = getimagesizefromstring($picture);
            $this->assertNotFalse($inner, 'an icon entry is not a picture');
            $this->assertSame([$entry['width'], $entry['height']], [$inner[0], $inner[1]]);
        }
        $this->assertSame([16, 32, 48], $sizes);
    }

    public function test_the_web_manifest_carries_the_real_name_the_yard_colours_and_icons_that_exist(): void
    {
        // The manifest is served by a route (see the content-type test below); its content is in resources/.
        $manifest = json_decode((string) $this->get('/site.webmanifest')->assertOk()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        preg_match('/--ix-top:\s*(#[0-9a-f]{6})/', $css, $top);
        preg_match('/--ix-bg:\s*(#[0-9a-f]{6})/', $css, $ground);

        $this->assertSame('InfoDot', $manifest['name']);
        $this->assertSame('InfoDot', $manifest['short_name']);
        $this->assertSame($top[1], $manifest['theme_color'], 'the theme colour is the top bar token');
        $this->assertSame($ground[1], $manifest['background_color'], 'the background is the light ground token');
        $this->assertCount(2, $manifest['icons']);
        foreach ($manifest['icons'] as $icon) {
            $info = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertNotFalse($info, $icon['src']);
            $this->assertSame($icon['sizes'], $info[0].'x'.$info[1]);
            $this->assertSame('image/png', $icon['type']);
        }
        $this->assertStringContainsString('content="'.$top[1].'"', (string) file_get_contents(resource_path('views/partials/head-icons.blade.php')));
    }

    public function test_every_layout_emits_the_same_icon_links_and_each_file_exists(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => Carbon::now()]);

        $pages = [
            'landing (public layout)' => $this->get('/')->assertOk()->getContent(),
            'about (public layout)' => $this->get('/about')->assertOk()->getContent(),
            'sign in (guest layout)' => $this->get('/login')->assertOk()->getContent(),
            'register (guest layout)' => $this->get('/register')->assertOk()->getContent(),
            'directory (app layout, visitor)' => $this->get('/businesses')->assertOk()->getContent(),
            'not found (error layout)' => $this->get('/no-such-page-'.uniqid())->assertNotFound()->getContent(),
            'home (app layout)' => $this->actingAs($user)->get('/home')->assertOk()->getContent(),
        ];

        $expected = $this->iconTags((string) $pages['landing (public layout)']);
        $this->assertCount(5, $expected, 'icon, PNG icon, touch icon, manifest and theme colour');

        foreach ($pages as $name => $html) {
            $this->assertSame($expected, $this->iconTags((string) $html), $name.' does not carry the shared icon links');
        }

        foreach ($expected as $tag) {
            if (preg_match('/href="([^"]+)"/', $tag, $m)) {
                $path = ltrim((string) parse_url($m[1], PHP_URL_PATH), '/');
                if ($path === 'site.webmanifest') {
                    continue;   // a route, not a file: test_the_web_manifest_is_served_by_the_application_with_its_content_type
                }
                $this->assertContains($path, self::ROOT_FILES, $tag);
                $this->assertFileExists(public_path($path));
                $this->assertGreaterThan(0, filesize(public_path($path)), $path.' is empty');
            }
        }
    }

    public function test_only_the_shared_partial_names_an_icon_and_every_full_page_includes_it(): void
    {
        foreach ((new Finder)->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $path = $file->getRelativePathname();
            $source = $file->getContents();
            if ($path === 'partials/head-icons.blade.php' || str_contains($path, 'vendor/mail') || str_starts_with($path, 'emails/')) {
                continue;
            }
            $this->assertSame(0, preg_match('/rel="(shortcut icon|icon|apple-touch-icon|manifest)"|name="theme-color"/', $source), $path.' names an icon itself; include partials.head-icons instead');

            // The hand-over page to another platform is a bare interstitial under its own stricter policy; it is the one exception.
            if (preg_match('/<head[\s>]/', $source) === 1 && $path !== 'launch.blade.php') {
                $this->assertStringContainsString("@include('partials.head-icons')", $source, $path.' is a full page without the shared icons');
            }
        }
    }

    public function test_the_web_manifest_is_served_by_the_application_with_its_content_type(): void
    {
        $response = $this->get('/site.webmanifest')->assertOk();

        $this->assertStringStartsWith('application/manifest+json', (string) $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('604800', (string) $response->headers->getCacheControlDirective('max-age'));
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertSame([], $response->headers->getCookies(), 'a publicly cached answer sets no cookie');
        $this->assertSame('InfoDot', $response->json('name'));

        // A browser that already holds it is told so.
        $this->get('/site.webmanifest', ['If-None-Match' => (string) $response->headers->get('ETag')])->assertStatus(304);

        // Every page points at the route.
        $this->assertStringContainsString('<link rel="manifest" href="'.route('site.webmanifest').'">', (string) $this->get('/login')->getContent());
    }

    public function test_no_static_copy_of_the_manifest_can_stand_in_front_of_the_route(): void
    {
        // A file in the web root is served by the web server before any route, with the server's own content type.
        $this->assertFileDoesNotExist(public_path('site.webmanifest'));

        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertSame(1, preg_match('/for F in ([^;]+); do/', $deploy, $m));
        $this->assertNotContains('site.webmanifest', preg_split('/\s+/', trim($m[1])), 'the deploy must not copy the manifest to the web root');
        $this->assertStringContainsString('rm -f "$DOCROOT/site.webmanifest"', $deploy, 'the copy earlier releases left in the web root is removed');
    }

    public function test_the_deploy_copies_every_icon_file_to_the_web_root(): void
    {
        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertSame(1, preg_match('/for F in ([^;]+); do/', $deploy, $m), 'the list of static files the docroot serves was not found');
        $listed = preg_split('/\s+/', trim($m[1]));

        foreach (self::ROOT_FILES as $file) {
            $this->assertContains($file, $listed, $file.' would not reach the live web root');
            $this->assertFileExists(public_path($file));
        }
    }
}
