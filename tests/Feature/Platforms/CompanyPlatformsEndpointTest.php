<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Support\Platforms;
use Illuminate\Support\Str;

/**
 * GET /api/ecosystem/companies/{uuid}/platforms: what a company holds, for the platform that asks with its own
 * secret (R-50). About the company only: never a person, never a price.
 */
class CompanyPlatformsEndpointTest extends PlatformTestCase
{
    private const FORMS = 'forms-service-secret-for-tests';

    private const ANALYTICS = 'analytics-service-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ecosystem.service_secrets' => ['forms' => self::FORMS, 'analytics' => self::ANALYTICS]]);
    }

    private function read(string $uuid, ?string $platform = 'forms', ?string $secret = self::FORMS, bool $header = false)
    {
        $headers = ($secret === null ? [] : ['Authorization' => 'Bearer '.$secret]) + ($header && $platform !== null ? ['X-Dot-Platform' => $platform] : []);

        return $this->withHeaders($headers)->getJson('/api/ecosystem/companies/'.$uuid.'/platforms'.($header || $platform === null ? '' : '?platform='.$platform));
    }

    public function test_it_lists_every_discoverable_platform_with_its_state_for_the_company(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');

        $response = $this->read($team->uuid)->assertOk()->assertJsonPath('platform', 'forms')->assertJsonPath('company.uuid', $team->uuid);
        $platforms = collect($response->json('platforms'))->keyBy('key');

        $expected = array_keys(Platforms::visible());
        sort($expected);
        $this->assertSame($expected, $platforms->keys()->sort()->values()->all());

        $this->assertSame(['key' => 'forms', 'name' => 'Dot.Forms', 'status' => 'active', 'entitled' => true, 'is_core' => false, 'access_mode' => 'everyone'], $platforms['forms']);
        $this->assertSame(['key' => 'finance', 'name' => 'Dot.Finance', 'status' => 'included', 'entitled' => true, 'is_core' => true], $platforms['finance']);
        $this->assertSame(['key' => 'hr', 'name' => 'Dot.HR', 'status' => 'free_while_pricing_finalised', 'entitled' => false, 'is_core' => false], $platforms['hr']);
        $this->assertSame('managed_in_platform', $platforms['mines']['status']);
        $this->assertSame('included', $platforms['infodot']['status']);
        $this->assertArrayNotHasKey('memory', $platforms->all());
    }

    public function test_the_states_follow_the_records(): void
    {
        $team = $this->company();
        $state = fn (string $key) => collect($this->read($team->uuid)->json('platforms'))->firstWhere('key', $key)['status'];
        $row = fn (array $values) => PlatformEntitlement::updateOrCreate(['team_id' => $team->id, 'platform_key' => 'hr'], $values + ['source' => 'paid', 'access_mode' => 'everyone']);

        PlatformPlan::create(['platform_key' => 'projects', 'slug' => 'free', 'name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);
        $this->assertSame('free_to_activate', $state('projects'));

        PlatformPlan::create(['platform_key' => 'agents', 'slug' => 'team', 'name' => 'Team', 'model' => 'subscription', 'amount_minor' => 9900, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);
        $this->assertSame('available_for_purchase', $state('agents'));
        PlatformPlan::where('platform_key', 'agents')->update(['trial_days' => 14]);
        $this->assertSame('trial_available', $state('agents'));

        foreach ([
            'activation_pending' => ['status' => 'pending'],
            'trial' => ['status' => 'trial', 'trial_ends_at' => now()->addDay()],
            'payment_required' => ['status' => 'payment_required'],
            'subscription_expired' => ['status' => 'expired'],
            'suspended' => ['status' => 'suspended'],
        ] as $expected => $values) {
            $row($values);
            $this->assertSame($expected, $state('hr'), $expected);
        }

        PlatformCatalogue::where('key', 'hr')->update(['operational_status' => 'maintenance']);
        $this->assertSame('temporarily_unavailable', $state('hr'));
        PlatformCatalogue::where('key', 'hr')->update(['operational_status' => 'not_launched']);
        $this->assertSame('not_launched', $state('hr'));
        PlatformCatalogue::where('key', 'hr')->update(['discoverable' => false]);
        $this->assertNull(collect($this->read($team->uuid)->json('platforms'))->firstWhere('key', 'hr'), 'a hidden platform is not listed');
    }

    public function test_access_mode_is_given_only_for_the_callers_own_platform(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms', 'analytics');
        $this->entitlements()->setAccessMode($team, 'forms', 'selected', $team->owner);

        $asForms = collect($this->read($team->uuid)->json('platforms'))->keyBy('key');
        $this->assertSame('selected', $asForms['forms']['access_mode']);
        $this->assertArrayNotHasKey('access_mode', $asForms['analytics']);

        $asAnalytics = collect($this->read($team->uuid, 'analytics', self::ANALYTICS, header: true)->assertOk()->assertJsonPath('platform', 'analytics')->json('platforms'))->keyBy('key');
        $this->assertSame('everyone', $asAnalytics['analytics']['access_mode']);
        $this->assertArrayNotHasKey('access_mode', $asAnalytics['forms']);
        $this->assertTrue($asAnalytics['forms']['entitled']);
    }

    public function test_it_never_returns_people_or_prices(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $this->activatePlatforms($team, 'forms');
        $this->entitlements()->setAccessMode($team, 'forms', 'selected', $team->owner);
        $this->entitlements()->grantUser($team, 'forms', $member, $team->owner);
        PlatformPlan::create(['platform_key' => 'hr', 'slug' => 'team', 'name' => 'Team', 'model' => 'subscription', 'amount_minor' => 123456, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);

        $body = $this->read($team->uuid)->assertOk()->getContent();

        foreach ([$member->email, $member->name, $member->uuid, $team->owner->email, $team->owner->name, '123456', 'ZAR', 'amount', 'granted_by'] as $absent) {
            $this->assertStringNotContainsString((string) $absent, $body);
        }
    }

    public function test_a_wrong_secret_a_missing_one_and_a_secret_for_another_platform_are_refused_alike(): void
    {
        $team = $this->company();

        $this->read($team->uuid, 'forms', 'not-the-secret')->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $this->read($team->uuid, 'forms', null)->assertStatus(401);
        $this->read($team->uuid, 'forms', self::ANALYTICS)->assertStatus(401);
        $this->read($team->uuid, 'analytics', self::FORMS)->assertStatus(401);
        $this->read($team->uuid, null, self::FORMS)->assertStatus(401);
        $this->read($team->uuid, 'hr', self::FORMS)->assertStatus(401);
        $this->read($team->uuid, 'no-such-platform', self::FORMS)->assertStatus(401);

        // An unknown company is not revealed to someone without the secret either.
        $this->read((string) Str::uuid(), 'forms', 'not-the-secret')->assertStatus(401);
    }

    public function test_an_unknown_company_is_a_404_and_a_platform_that_is_switched_off_cannot_ask(): void
    {
        $this->read((string) Str::uuid())->assertNotFound();
        $this->read('not-a-uuid')->assertNotFound();

        config(['ecosystem.platforms.forms.active' => false]);
        $this->read($this->company()->uuid)->assertForbidden();
    }

    public function test_a_personal_workspace_holds_the_core_only(): void
    {
        $person = $this->person();

        $platforms = collect($this->read($person->currentTeam->uuid)->assertOk()->assertJsonPath('company.personal', true)->json('platforms'))->keyBy('key');

        $this->assertTrue($platforms['files']['entitled']);
        $this->assertSame('included', $platforms['files']['status']);
        $this->assertFalse($platforms['forms']['entitled']);
        $this->assertSame('company_required', $platforms['forms']['status']);
    }

    public function test_it_is_throttled_like_the_redeem_call(): void
    {
        $route = app('router')->getRoutes()->getByName('api.ecosystem.companies.platforms');

        $this->assertContains('throttle:ecosystem-redeem', $route->gatherMiddleware());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }
}
