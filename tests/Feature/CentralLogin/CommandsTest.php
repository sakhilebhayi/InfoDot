<?php

namespace Tests\Feature\CentralLogin;

use App\Models\DotPlatform;
use App\Models\PlatformBranding;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;

/** R-54: the two owner-run commands. Both are read-only unless told otherwise, and neither touches a real client. */
class CommandsTest extends CentralLoginTestCase
{
    public function test_register_all_lists_what_would_be_registered_and_creates_nothing(): void
    {
        $platforms = DotPlatform::count();
        $clients = Client::count();

        $this->artisan('sso:platform', ['action' => 'register-all', '--dry-run' => true])
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $out = $this->runAll();
        $this->assertStringContainsString('https://design.infodot.co.za/sso/callback', $out);
        $this->assertStringContainsString('would register', $out);
        $this->assertStringContainsString('registered (active)', $out, 'Central is registered by the test setup');
        $this->assertStringContainsString('no receiver yet', $out, 'Charts has none');
        $this->assertStringNotContainsString('dopemine', $out);
        $this->assertStringNotContainsString('memory.infodot', $out);
        foreach (['plug', 'billing', 'farms'] as $gone) {
            $this->assertStringNotContainsString($gone, strtolower($out));
        }

        $this->assertSame($platforms, DotPlatform::count());
        $this->assertSame($clients, Client::count());
    }

    public function test_register_all_with_no_flag_is_still_a_dry_run(): void
    {
        $clients = Client::count();

        $this->assertStringContainsString('Dry run only', $this->runAll(withFlag: false));
        $this->assertSame($clients, Client::count());
    }

    public function test_branding_seed_is_a_dry_run_by_default(): void
    {
        $this->artisan('branding:seed')->expectsOutputToContain('Dry run only')->assertSuccessful();

        $this->assertSame(0, PlatformBranding::count());
    }

    public function test_branding_seed_apply_creates_real_rows_once_and_never_overwrites_an_edit(): void
    {
        $this->artisan('branding:seed', ['--apply' => true])->assertSuccessful();

        $row = PlatformBranding::firstOrFail();
        $this->assertSame($this->platform->id, $row->dot_platform_id);
        $this->assertSame('#f2a70b', $row->primary_color);
        $this->assertSame('dark', $row->mode);
        $this->assertSame('Welcome back to Dot.Central', $row->headline_login);
        $this->assertSame('Get started with Dot.Central', $row->headline_register);
        $this->assertSame('The control room for a mining shift', $row->description, 'the catalogue tagline, not an invented line');
        $this->assertSame(['central.test'], $row->approved_redirect_domains);
        $this->assertSame(1, $row->version);

        $row->update(['headline_login' => 'Edited by an administrator', 'version' => 4]);
        $this->artisan('branding:seed', ['--apply' => true])->expectsOutputToContain('left untouched')->assertSuccessful();

        $this->assertSame(1, PlatformBranding::count());
        $this->assertSame('Edited by an administrator', $row->fresh()->headline_login);
        $this->assertSame(4, $row->fresh()->version);
    }

    public function test_branding_seed_skips_platforms_that_are_not_active_or_have_no_recorded_identity(): void
    {
        $this->platform->update(['status' => 'suspended']);
        $this->artisan('branding:seed', ['--apply' => true])->expectsOutputToContain('nothing to brand')->assertSuccessful();
        $this->assertSame(0, PlatformBranding::count());

        $this->platform->update(['status' => 'active', 'slug' => 'infodot']);
        $this->artisan('branding:seed', ['--apply' => true])->expectsOutputToContain('skipped')->assertSuccessful();
        $this->assertSame(0, PlatformBranding::count());
    }

    public function test_every_seeded_identity_is_valid(): void
    {
        foreach (config('platform_branding') as $slug => $identity) {
            $this->assertNotNull(config('ecosystem.platforms.'.$slug), $slug.' is a registry key');
            $this->assertNotTrue(config('ecosystem.platforms.'.$slug.'.internal', false), $slug.' is user-facing');
            foreach (['primary', 'secondary', 'accent'] as $k) {
                $this->assertMatchesRegularExpression(PlatformBranding::HEX, $identity[$k], $slug.' '.$k);
            }
            $this->assertContains($identity['mode'], PlatformBranding::MODES);
        }
    }

    private function runAll(bool $withFlag = true): string
    {
        Artisan::call('sso:platform', ['action' => 'register-all'] + ($withFlag ? ['--dry-run' => true] : []));

        return Artisan::output();
    }
}
