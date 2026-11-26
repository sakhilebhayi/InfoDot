<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner rule (2026-11-26): InfoDot's logo is the yellow-and-white one (resources/img/brand/logo_white.png). The old
 * blue-and-grey logo is never used anywhere again, so its files must not come back and nothing may point at them.
 */
class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    private const RETIRED = [
        'resources/img/brand/logo-master.png',
        'resources/img/apps/infodot.png',
        'resources/img/landing/platforms/infodot.png',
    ];

    public function test_the_blue_and_grey_logo_files_are_gone(): void
    {
        foreach (self::RETIRED as $path) {
            $this->assertFileDoesNotExist(base_path($path), "{$path} is the retired blue-and-grey logo");
        }
    }

    public function test_nothing_in_the_application_points_at_the_retired_logo(): void
    {
        $hits = [];
        foreach (['app', 'resources/views', 'resources/js', 'resources/css', 'config'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $source = (string) file_get_contents($file->getPathname());
                foreach (['logo-master.png', 'apps/infodot.png', 'platforms/infodot.png'] as $name) {
                    if (str_contains($source, $name)) {
                        $hits[] = str_replace(base_path().'/', '', $file->getPathname()).' mentions '.$name;
                    }
                }
            }
        }

        $this->assertSame([], $hits);
    }

    public function test_the_homepage_shows_infodot_with_the_yellow_and_white_logo_on_a_dark_plate(): void
    {
        $this->get('/')->assertOk()->assertSee('<span class="hp-lead__plate"><img', false);

        // Vite is faked in tests (empty src), so the source is checked directly: the Start card's logo is the yellow-and-white one.
        $this->assertStringContainsString("'logo' => Vite::asset('resources/img/brand/logo_white.png')", (string) file_get_contents(app_path('Support/BusinessTools.php')));
    }
}
