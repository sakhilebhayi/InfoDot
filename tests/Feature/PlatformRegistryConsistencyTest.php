<?php

namespace Tests\Feature;

use App\Livewire\EcosystemWidget;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The registry in config/ecosystem.php is the one list of Dot platforms. Three platforms were discontinued on
 * 2026-11-13 and taken out of it; these tests pin the list and prove that nothing else still carries a platform the
 * registry does not: no logo, no manifest entry, no switcher or register group, no service secret, no launch, no
 * redeem. A platform that leaves the registry later is caught the same way, without being named here.
 */
class PlatformRegistryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['agents', 'analytics', 'auction', 'central', 'charts', 'design', 'docs', 'dopemine', 'ehail', 'emall', 'engage', 'files', 'finance', 'forms', 'hr', 'infodot', 'memory', 'mines', 'notify', 'press', 'projects', 'pulse', 'sheet', 'tasks', 'tutor'];

    private function user(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    /** @return list<string> */
    private function registryKeys(): array
    {
        $keys = array_keys(config('ecosystem.platforms'));
        sort($keys);

        return $keys;
    }

    public function test_the_registry_holds_exactly_the_platforms_that_exist(): void
    {
        $this->assertSame(self::KEYS, $this->registryKeys());
    }

    public function test_every_logo_file_belongs_to_a_registered_platform(): void
    {
        foreach (['img/apps', 'img/landing/platforms'] as $folder) {
            $logos = array_map(fn (string $path) => basename($path, '.png'), glob(resource_path($folder.'/*.png')));
            sort($logos);

            $this->assertSame([], array_values(array_diff($logos, self::KEYS)), $folder.' holds a logo for a platform that is not in the registry');
        }
    }

    public function test_the_committed_build_manifest_lists_no_logo_of_an_unregistered_platform(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        foreach (array_keys($manifest) as $source) {
            if (preg_match('#^resources/img/(?:apps|landing/platforms)/([a-z]+)\.png$#', $source, $m)) {
                $this->assertContains($m[1], self::KEYS, $source);
            }
        }
    }

    public function test_only_registered_platforms_can_be_given_a_service_secret(): void
    {
        $source = (string) file_get_contents(config_path('ecosystem.php'));
        $this->assertSame(1, preg_match("/'service_secrets' => collect\(\[(.+?)\]\)/s", $source, $m));
        preg_match_all("/'([a-z]+)'/", $m[1], $names);

        $this->assertNotEmpty($names[1]);
        $this->assertSame([], array_values(array_diff($names[1], self::KEYS)));
    }

    public function test_the_register_groups_hold_every_visible_platform_and_nothing_else(): void
    {
        $visible = array_keys(Platforms::visible());
        $grouped = collect((new EcosystemWidget)->groups())->flatMap(fn (array $group) => array_keys($group['members']))->all();

        $this->assertEqualsCanonicalizing(array_values(array_diff($visible, ['infodot'])), $grouped);
    }

    public function test_the_switcher_groups_name_registered_platforms_only(): void
    {
        $blade = (string) file_get_contents(resource_path('views/livewire/dot-switcher.blade.php'));
        $this->assertSame(1, preg_match('/\$groups = \[(.+?)\n    \];/s', $blade, $m));
        preg_match_all("/\[('[a-z]+'(?:, '[a-z]+')*)\]\],/", $m[1], $lists);
        $keys = collect($lists[1])->flatMap(fn (string $list) => explode(', ', str_replace("'", '', $list)))->all();

        $this->assertEqualsCanonicalizing(array_values(array_diff(array_keys(Platforms::visible()), ['infodot'])), $keys);
    }

    public function test_the_landing_page_shows_only_platforms_from_the_registry(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/Dot\.[A-Za-z]+/', $html, $m);
        $names = array_values(array_unique($m[0]));
        $known = array_merge(array_column(config('ecosystem.platforms'), 'name'), ['Dot.Eco']);   // "Dot.Eco-system" is the ecosystem's own name

        $this->assertNotEmpty($names);
        $this->assertSame([], array_values(array_diff($names, $known)));

        preg_match_all('/<li[^>]*>\s*<img[^>]* alt="(Dot\.[^"]+)"/', $html, $wall);
        $expected = collect(config('ecosystem.platforms'))
            ->filter(fn (array $p, string $key) => ($p['active'] ?? false) && ! ($p['internal'] ?? false) && ! in_array($key, ['infodot', 'agents'], true))
            ->pluck('name')->values()->all();
        $this->assertSame($expected, $wall[1], 'the logo wall is the live, public platforms of the registry, in its order');
    }

    public function test_the_platforms_page_lists_only_platforms_from_the_registry(): void
    {
        $html = $this->actingAs($this->user())->get('/platforms')->assertOk()->getContent();

        preg_match_all('/Dot\.[A-Za-z]+/', $html, $m);
        $names = array_values(array_unique($m[0]));

        $this->assertNotEmpty($names);
        $this->assertSame([], array_values(array_diff($names, array_column(config('ecosystem.platforms'), 'name'))));
        $this->assertSame(count(self::KEYS) - 1 - count(array_intersect(self::KEYS, Platforms::INTERNAL)), substr_count($html, 'class="ix-regrow '), 'one row per visible platform other than InfoDot');
    }

    public function test_a_platform_outside_the_registry_cannot_be_opened_and_no_token_is_minted(): void
    {
        $user = $this->user();

        // Today's answer for any key the registry does not hold: the "not available" page, never a handoff.
        $this->actingAs($user)->get('/go/retired')->assertStatus(503)->assertDontSee('name="token"', false);

        $this->assertNull(EcosystemHandoff::launchUrl($user, 'retired'));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_a_platform_outside_the_registry_cannot_redeem_even_with_a_secret_set_for_it(): void
    {
        // The host may still carry a leftover ECOSYSTEM_SECRET_* line for a platform that has gone.
        config(['ecosystem.service_secrets' => ['retired' => 'leftover-secret-on-the-host']]);
        $token = $this->user()->createToken(EcosystemHandoff::tokenName('retired'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer leftover-secret-on-the-host'])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'retired'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'invalid_request']);

        $this->assertNotNull(PersonalAccessToken::findToken($token), 'a refused redeem does not spend the token');
    }
}
