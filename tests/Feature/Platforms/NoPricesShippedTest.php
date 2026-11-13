<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformBundle;
use App\Models\PlatformPlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/**
 * No price is ever invented (R-50 decision 4). The application ships with no plan and no bundle, and nothing in its
 * code, configuration, seeders, factories or data files carries an amount: a price exists only as a row that a
 * platform administrator typed and approved. This test reads the codebase to prove it.
 */
class NoPricesShippedTest extends AdminCentreTestCase
{
    /** Everything that ships, except the tests (which type their own amounts through the admin service). */
    private function shipped(): Finder
    {
        return (new Finder)->files()->in([app_path(), base_path('config'), base_path('database'), base_path('routes'), resource_path('views')])->name(['*.php', '*.json']);
    }

    public function test_a_fresh_installation_has_no_plans_and_no_bundles(): void
    {
        $this->assertSame(0, PlatformPlan::count());
        $this->assertSame(0, PlatformBundle::count());
        $this->assertSame(0, DB::table('platform_bundle_items')->count());

        $this->artisan('platforms:sync')->assertSuccessful();
        $this->artisan('db:seed', ['--force' => true]);

        $this->assertSame(0, PlatformPlan::count(), 'the sync or a seeder created a plan');
        $this->assertSame(0, PlatformBundle::count(), 'the sync or a seeder created a bundle');
    }

    public function test_no_shipped_file_assigns_an_amount_or_a_currency_to_a_plan_or_bundle(): void
    {
        $offenders = [];

        foreach ($this->shipped() as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            $code = (string) preg_replace('#/\*.*?\*/|(?<![:\'"])//[^\n]*#s', '', $file->getContents());

            // amount_minor (or the per-person amount) given a number, or a currency given a code, anywhere in shipped code.
            if (preg_match('/[\'"]?(?:extra_user_)?amount_minor[\'"]?\s*(?:=>|=|:)\s*[\'"]?\d/', $code)
                || (preg_match('/[\'"]currency[\'"]\s*(?:=>|=|:)\s*[\'"][A-Za-z]{3}[\'"]/', $code) && preg_match('/PlatformPlan|PlatformBundle|platform_plans|platform_bundles|platform_subscriptions/', $code))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'a shipped file carries a plan amount or currency');
    }

    public function test_plans_and_bundles_are_created_in_one_place_and_never_by_a_seeder_factory_or_migration(): void
    {
        $writers = [];

        foreach ($this->shipped() as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            $code = $file->getContents();

            if (preg_match('/new PlatformPlan\(|PlatformPlan::(create|insert|firstOrCreate|updateOrCreate|forceCreate|factory)|new PlatformBundle\(|PlatformBundle::(create|insert|firstOrCreate|updateOrCreate|forceCreate|factory)|table\([\'"]platform_(plans|bundles)[\'"]\)\s*->\s*(insert|upsert|updateOrInsert)/', $code)) {
                $writers[] = $path;
            }
        }

        $this->assertSame(['app/Services/Platforms/CatalogueAdmin.php'], $writers);
        $this->assertFileDoesNotExist(database_path('factories/PlatformPlanFactory.php'));
        $this->assertFileDoesNotExist(database_path('factories/PlatformBundleFactory.php'));
    }

    public function test_the_configuration_holds_no_price_and_the_forms_suggest_none(): void
    {
        $flat = collect(Arr::dot((array) config('platform_subscriptions')));
        $this->assertSame([], $flat->keys()->filter(fn ($key) => preg_match('/amount|price|currency|fee|cost/i', (string) $key))->values()->all());

        $admin = $this->administrator();
        $page = $this->actingAs($admin)->get('/admin/platforms/catalogue/hr')->assertOk()->getContent();
        $bundles = $this->actingAs($admin)->get('/admin/platforms/bundles')->assertOk()->getContent();

        foreach ([$page, $bundles] as $html) {
            $this->assertMatchesRegularExpression('/name="amount_minor"[^>]*value=""/', $html, 'the amount field arrives filled in');
            $this->assertMatchesRegularExpression('/name="currency"[^>]*value=""/', $html, 'the currency field arrives filled in');
            $this->assertDoesNotMatchRegularExpression('/name="(amount_minor|currency)"[^>]*placeholder=/', $html);
        }
    }

    public function test_the_catalogue_data_file_describes_platforms_and_prices_nothing(): void
    {
        $entries = require database_path('data/platform_catalogue.php');

        foreach ($entries as $key => $entry) {
            $this->assertSame([], array_values(array_filter(array_keys($entry), fn ($field) => preg_match('/amount|price|currency|plan|trial/i', (string) $field) === 1)), $key);
        }
    }
}
