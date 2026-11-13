<?php

namespace Tests\Feature\Platforms;

use App\Models\DotPlatform;
use App\Models\PlatformCatalogue;
use App\Services\Ops\OpsCheck;
use App\Services\Platforms\CatalogueSync;
use App\Support\PlatformCategories;
use App\Support\Platforms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The registry says which platforms exist; the catalogue says what each one is. These tests keep the two from
 * drifting: a platform people can see always has a written entry, an entry never outlives its platform, and the
 * OIDC registry's slugs are catalogue keys, because that slug is how the OAuth door finds the platform to judge.
 */
class PlatformCatalogueConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const CORE = ['docs', 'files', 'finance', 'infodot', 'tasks'];

    /** @return array<string, array<string, mixed>> */
    private function entries(): array
    {
        return app(CatalogueSync::class)->entries();
    }

    public function test_every_visible_platform_has_a_written_entry_and_every_entry_has_a_platform(): void
    {
        $visible = array_keys(Platforms::visible());
        $written = array_keys($this->entries());
        sort($visible);
        sort($written);

        $this->assertSame($visible, $written);
    }

    public function test_every_entry_is_complete_and_named_as_the_registry_names_it(): void
    {
        foreach ($this->entries() as $key => $entry) {
            $this->assertSame(config("ecosystem.platforms.$key.name"), $entry['name'], $key);

            foreach (['tagline', 'description', 'benefit', 'audience'] as $field) {
                $this->assertNotSame('', trim((string) ($entry[$field] ?? '')), "$key has no $field");
            }

            $this->assertGreaterThanOrEqual(3, count($entry['capabilities']), $key);
            $this->assertLessThanOrEqual(6, count($entry['capabilities']), $key);
            $this->assertNotEmpty($entry['categories'], $key);
            $this->assertSame([], array_values(array_diff($entry['categories'], array_keys(PlatformCategories::ALL))), "$key is filed under a category that does not exist");
            $this->assertSame([], array_values(array_diff($entry['connects_with'], array_keys(Platforms::visible()))), "$key connects with something people cannot see");
            $this->assertNotContains($key, $entry['connects_with'], "$key connects with itself");
        }
    }

    public function test_there_are_ten_categories(): void
    {
        $this->assertCount(10, PlatformCategories::ALL);
    }

    public function test_the_free_core_is_exactly_infodot_files_docs_tasks_and_finance(): void
    {
        $core = array_keys(array_filter($this->entries(), fn (array $entry) => $entry['is_core'] === true));
        sort($core);

        $this->assertSame(self::CORE, $core);

        $rows = PlatformCatalogue::where('is_core', true)->orderBy('key')->pluck('key')->all();
        $this->assertSame(self::CORE, $rows);
    }

    public function test_the_migration_filled_the_catalogue_with_every_visible_platform_as_operational(): void
    {
        $keys = PlatformCatalogue::orderBy('key')->pluck('key')->all();
        $visible = array_keys(Platforms::visible());
        sort($visible);

        $this->assertSame($visible, $keys);
        $this->assertSame(0, PlatformCatalogue::where('operational_status', '!=', 'operational')->count());
        $this->assertSame(0, PlatformCatalogue::where('discoverable', false)->count());
        $this->assertSame(0, PlatformCatalogue::whereIn('key', Platforms::INTERNAL)->count(), 'bridge services are never in the catalogue');
    }

    public function test_sync_adds_what_is_missing_and_never_overwrites_an_administrators_edit(): void
    {
        PlatformCatalogue::where('key', 'forms')->update(['tagline' => 'Edited by an administrator', 'operational_status' => 'maintenance']);
        PlatformCatalogue::where('key', 'hr')->delete();

        $this->artisan('platforms:sync')->expectsOutputToContain('hr')->assertSuccessful();

        $this->assertSame('Edited by an administrator', PlatformCatalogue::where('key', 'forms')->value('tagline'));
        $this->assertSame('maintenance', PlatformCatalogue::where('key', 'forms')->value('operational_status'));
        $this->assertSame('Dot.HR', PlatformCatalogue::where('key', 'hr')->value('name'));

        $count = DB::table('platform_catalogue')->count();
        $this->artisan('platforms:sync')->assertSuccessful();
        $this->assertSame($count, DB::table('platform_catalogue')->count());
    }

    public function test_a_registered_platform_nobody_has_described_is_added_but_not_offered(): void
    {
        config(['ecosystem.platforms.newthing' => ['name' => 'Dot.Newthing', 'url' => 'https://newthing.infodot.co.za', 'active' => true]]);

        $this->assertSame(['newthing'], app(CatalogueSync::class)->sync());

        $row = PlatformCatalogue::where('key', 'newthing')->firstOrFail();
        $this->assertFalse($row->discoverable);
        $this->assertSame('not_launched', $row->operational_status);
    }

    public function test_every_registered_oidc_platform_slug_is_a_registry_key(): void
    {
        // The table is empty in a fresh test database; the rule is pinned where slugs are made (see the next test).
        foreach (DotPlatform::pluck('slug') as $slug) {
            $this->assertNotNull(config('ecosystem.platforms.'.$slug), "dot_platforms.slug [$slug] is not a registry key");
        }

        $this->assertTrue(true);
    }

    public function test_ops_check_counts_an_oidc_platform_whose_slug_is_not_a_registry_key(): void
    {
        $row = fn (string $slug) => DotPlatform::create(['name' => $slug, 'slug' => $slug, 'domain' => "https://$slug.test", 'oauth_client_id' => 'client-'.$slug, 'redirect_uris' => [], 'scopes' => ['openid'], 'status' => 'active']);

        $row('central');
        $this->assertSame(0, app(OpsCheck::class)->run()['sso_platform_slug_not_in_registry']);

        $row('sheets');
        $this->assertSame(1, app(OpsCheck::class)->run()['sso_platform_slug_not_in_registry']);
    }

    public function test_an_oidc_platform_cannot_be_registered_under_a_slug_the_registry_does_not_hold(): void
    {
        $this->artisan('sso:platform', ['action' => 'register', 'slug' => 'not-a-platform', '--name' => 'Nope', '--domain' => 'https://nope.infodot.co.za', '--redirect' => ['https://nope.infodot.co.za/sso/callback']])
            ->assertFailed();

        $this->assertSame(0, DotPlatform::count());

        $this->artisan('sso:platform', ['action' => 'register', 'slug' => 'central', '--name' => 'Dot.Central', '--domain' => 'https://central.infodot.co.za', '--redirect' => ['https://central.infodot.co.za/sso/callback']])
            ->assertSuccessful();

        $this->assertSame(['central'], DotPlatform::pluck('slug')->all());
    }
}
