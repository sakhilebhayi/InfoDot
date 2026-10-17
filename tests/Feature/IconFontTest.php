<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The app layout loads exactly one icon font from Google Fonts: Material Symbols
 * ROUNDED. The apps menu, the platforms widget and the analytics page asked for
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

    public function test_the_app_layout_loads_the_rounded_icon_font(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('family=Material+Symbols+Rounded', $layout);
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
        }
    }
}
