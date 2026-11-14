<?php

namespace Tests\Feature\Platforms;

use App\Models\DotPlatform;
use App\Models\PlatformCatalogue;
use App\Services\Platforms\EntitlementOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The staff infrastructure view (R-50 section 9): only facts InfoDot has recorded, only for platform administrators.
 * Who may not open it at all is also covered, for every route at once, in AdminCentreAccessTest.
 */
class AdminInfrastructureTest extends AdminCentreTestCase
{
    private function page(): string
    {
        return (string) $this->actingAs($this->administrator())->get('/admin/platforms/infrastructure')->assertOk()->getContent();
    }

    /** The table row of one platform, as text. */
    private function row(string $html, string $key): string
    {
        $this->assertSame(1, preg_match('/<tr data-platform="'.preg_quote($key, '/').'">(.*?)<\/tr>/s', $html, $m), "no row for $key");

        return trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
    }

    private function sso(string $slug, array $values = []): DotPlatform
    {
        return DotPlatform::create($values + [
            'name' => $slug, 'slug' => $slug, 'domain' => $slug.'.example', 'oauth_client_id' => 'client-'.$slug.'-'.Str::random(6),
            'redirect_uris' => [], 'scopes' => ['openid'], 'status' => 'active',
        ]);
    }

    public function test_each_catalogue_platform_has_a_row_and_nothing_is_invented_for_a_quiet_one(): void
    {
        $html = $this->page();

        foreach (PlatformCatalogue::pluck('key') as $key) {
            $this->assertStringContainsString('data-platform="'.$key.'"', $html, $key);
        }

        $row = $this->row($html, 'forms');
        $this->assertStringContainsString('Operational', $row);
        $this->assertStringContainsString('Not registered', $row);
        $this->assertStringContainsString('None recorded', $row);
        $this->assertStringContainsString('Nothing sent yet', $row);
        // No uptime, no percentage, no "healthy": InfoDot does not probe, and the page says so.
        $this->assertStringContainsString('No uptime figure.', $html);
        $this->assertDoesNotMatchRegularExpression('/uptime\s*[:\d]|\d\s*%|healthy|\bonline\b/i', preg_replace('/No uptime figure\.[^<]*/', '', strip_tags($html)));
    }

    public function test_the_catalogue_status_an_administrator_set_and_when(): void
    {
        $admin = $this->administrator();
        PlatformCatalogue::where('key', 'hr')->update(['operational_status' => 'maintenance', 'discoverable' => false]);
        DB::table('platform_catalogue_events')->insert(['actor_id' => $admin->id, 'subject_type' => 'platform', 'subject_key' => 'hr', 'type' => 'updated', 'before' => '{}', 'after' => '{}', 'reason' => self::WHY, 'created_at' => now()->subDay()]);

        $row = $this->row($this->page(), 'hr');

        $this->assertStringContainsString('Maintenance', $row);
        $this->assertStringContainsString('Changed '.now()->subDay()->format('j M Y'), $row);
        $this->assertStringContainsString('Hidden from businesses', $row);
    }

    public function test_sign_in_registration_present_missing_suspended_and_the_last_sign_in(): void
    {
        $this->sso('forms', ['last_authenticated_at' => now()->subHours(3)]);
        $this->sso('tasks', ['status' => 'suspended']);
        DB::table('sso_platform_sessions')->insert(['user_id' => $this->person()->id, 'dot_platform_id' => DotPlatform::where('slug', 'forms')->value('id'), 'last_issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $html = $this->page();

        $forms = $this->row($html, 'forms');
        $this->assertStringContainsString('Registered', $forms);
        $this->assertStringContainsString('1 person has signed in this way', $forms);
        $this->assertStringContainsString(now()->subHours(3)->format('j M Y, H:i'), $forms);
        $this->assertStringContainsString('Registered, suspended', $this->row($html, 'tasks'));
        $this->assertStringContainsString('Not registered', $this->row($html, 'hr'));
        // A platform with its own sign-in is not "missing" a registration it never needed.
        $this->assertStringContainsString('Not used: its own sign-in', $this->row($html, 'mines'));
    }

    public function test_registry_state_and_whether_a_shared_secret_is_set_without_ever_showing_it(): void
    {
        $secret = Str::random(40);
        config(['ecosystem.service_secrets' => ['forms' => $secret], 'ecosystem.platforms.projects.active' => false]);
        $html = $this->page();

        $this->assertStringContainsString('shared secret set', $this->row($html, 'forms'));
        $this->assertStringContainsString('shared secret missing', $this->row($html, 'hr'));
        $this->assertStringContainsString('Switched off', $this->row($html, 'projects'));
        $this->assertStringNotContainsString($secret, $html);
    }

    public function test_delivery_state_per_platform_from_the_outbox(): void
    {
        $team = $this->company();
        $admin = $this->administrator();
        $this->entitlements()->grantComplimentary($team, 'forms', $admin, null, self::WHY);
        $this->entitlements()->grantComplimentary($team, 'hr', $admin, null, self::WHY);
        $this->entitlements()->grantComplimentary($team, 'projects', $admin, null, self::WHY);
        $this->entitlements()->grantComplimentary($team, 'agents', $admin, null, self::WHY);

        DB::table('entitlement_outbox')->where('platform_key', 'forms')->update(['sent_at' => now()->subMinutes(30)]);
        DB::table('entitlement_outbox')->where('platform_key', 'hr')->update(['failed_at' => now(), 'attempts' => 1, 'last_error' => 'HTTP 422']);
        DB::table('entitlement_outbox')->where('platform_key', 'projects')->update(['sent_at' => now(), 'last_error' => EntitlementOutbox::NOT_IMPLEMENTED]);
        DB::table('entitlement_outbox')->where('platform_key', 'agents')->update(['attempts' => 2, 'last_error' => 'unreachable']);
        $html = $this->page();

        $forms = $this->row($html, 'forms');
        $this->assertStringContainsString('1 accepted', $forms);
        $this->assertStringContainsString('Last accepted '.now()->subMinutes(30)->format('j M Y, H:i'), $forms);

        $hr = $this->row($html, 'hr');
        $this->assertStringContainsString('1 parked', $hr);
        $this->assertStringContainsString('Last answer: HTTP 422', $hr);

        $projects = $this->row($html, 'projects');
        $this->assertStringContainsString('0 accepted', $projects);
        $this->assertStringContainsString('1 event closed because the platform has no receiver yet', $projects);

        $agents = $this->row($html, 'agents');
        $this->assertStringContainsString('1 waiting', $agents);
        $this->assertStringContainsString('Last answer: unreachable', $agents);
        $this->assertStringContainsString('None accepted yet', $agents);
    }

    public function test_a_registered_platform_with_no_catalogue_entry_is_named(): void
    {
        config(['ecosystem.platforms.orphan' => ['name' => 'Orphan', 'url' => 'https://orphan.infodot.co.za', 'icon' => 'apps']]);

        $this->assertStringContainsString('no catalogue entry', $this->page());
        $this->assertStringContainsString('orphan', strip_tags($this->page()));
    }

    public function test_reading_it_changes_nothing_and_it_is_not_on_a_business_page(): void
    {
        $team = $this->company();
        $before = [DB::table('platform_catalogue_events')->count(), DB::table('entitlement_outbox')->count(), DB::table('platform_entitlements')->count(), DB::table('dot_platforms')->count()];

        $this->page();
        $this->assertSame($before, [DB::table('platform_catalogue_events')->count(), DB::table('entitlement_outbox')->count(), DB::table('platform_entitlements')->count(), DB::table('dot_platforms')->count()]);

        foreach (['/analytics', '/platforms', '/home'] as $path) {
            $html = (string) $this->actingAs($team->owner)->get($path)->getContent();
            $this->assertStringNotContainsString('admin/platforms/infrastructure', $html, $path);
            $this->assertStringNotContainsString('data-infrastructure', $html, $path);
        }
    }

    public function test_the_tab_is_in_the_admin_centre_for_administrators_and_the_roles_below_are_refused(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('href="'.route('admin.platforms.infrastructure').'"', $html);
        $this->assertStringContainsString('Infrastructure</a>', $html);

        $team = $this->company();
        foreach ([$team->owner, $this->memberOf($team, 'admin'), $this->memberOf($team, 'manager'), $this->memberOf($team), $this->moderator(), $this->person()] as $person) {
            $this->actingAs($person)->get('/admin/platforms/infrastructure')->assertForbidden();
        }
    }
}
