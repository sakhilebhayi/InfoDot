<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * On 2026-10-17 nothing worked after login: the Livewire script was requested
 * from the site root (config asset_url was APP_URL) and came back as HTML, a stale
 * published copy of Livewire's assets was in public/, Echo threw on a missing
 * Reverb key, and Alpine was started twice. These pin each of those down.
 */
class FrontendScriptsTest extends TestCase
{
    use RefreshDatabase;

    private function signedIn(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_the_livewire_script_tag_points_at_the_script_not_the_site_root(): void
    {
        config(['app.url' => 'https://infodot.example']);

        $html = $this->actingAs($this->signedIn())->get('/questions')->assertOk()->getContent();

        // Livewire's tag now carries the per-request CSP nonce between src and data-csrf (R-19).
        preg_match('/<script src="([^"]+)"[^>]*data-csrf/', $html, $m);
        $this->assertNotEmpty($m, 'Livewire script tag not found');
        $this->assertMatchesRegularExpression('#^/livewire/livewire(\.min)?\.js\?id=#', $m[1]);
    }

    public function test_the_livewire_script_is_served_as_javascript(): void
    {
        // Livewire registers /livewire/livewire.js with debug on and /livewire/livewire.min.js with it off, so ask for the one the page emits.
        $html = $this->actingAs($this->signedIn())->get('/questions')->assertOk()->getContent();
        preg_match('#/livewire/livewire(\.min)?\.js#', $html, $m);
        $this->assertNotEmpty($m, 'Livewire script tag not found');

        $served = $this->get($m[0])->assertOk();
        $this->assertStringContainsString('javascript', (string) $served->headers->get('content-type'));
    }

    public function test_no_stale_published_livewire_assets_shadow_the_package(): void
    {
        $this->assertDirectoryDoesNotExist(public_path('vendor/livewire'));
    }

    public function test_the_app_layout_does_not_load_a_second_alpine(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/<script[^>]+alpinejs/i', $layout);
    }

    public function test_echo_is_only_created_when_a_reverb_key_is_configured(): void
    {
        $js = file_get_contents(resource_path('js/bootstrap.js'));

        $this->assertMatchesRegularExpression('/if \(import\.meta\.env\.VITE_REVERB_APP_KEY\)\s*\{\s*window\.Echo/', $js);
    }
}
