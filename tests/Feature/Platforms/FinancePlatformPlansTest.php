<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformPlan;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** GET /api/finance/platform-plans (R-50): signed, shut while the secret is unset, and only ever what is on sale. */
class FinancePlatformPlansTest extends AdminCentreTestCase
{
    private function read(string $key, ?int $timestamp = null, ?string $signature = null, string $method = 'GET'): TestResponse
    {
        $timestamp ??= time();

        return $this->call($method, '/api/finance/platform-plans', [], [], [], [
            'HTTP_X_INFODOT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_INFODOT_SIGNATURE' => $signature ?? Signature::sign($key, $timestamp, ''),
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }

    public function test_it_lists_only_approved_priced_plans_in_minor_units(): void
    {
        $key = $this->financeKey();
        $admin = $this->administrator();
        $this->plan('hr', ['name' => 'Team', 'trial_days' => 14], true, $admin);
        $this->plan('hr', ['name' => 'Large', 'amount_minor' => 99900, 'interval' => 'year'], true, $admin);
        $this->plan('projects', ['name' => 'Draft'], false, $admin);                           // not approved
        $this->plan('forms', ['name' => 'Retired', 'active' => false], true, $admin);          // not on sale
        $this->plan('agents', ['name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'interval' => null], true, $admin); // costs nothing
        PlatformPlan::create(['platform_key' => 'design', 'slug' => 'unpriced', 'name' => 'Unpriced', 'model' => 'subscription', 'approved_at' => now(), 'active' => true]);

        $response = $this->read($key)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $plans = $response->json('plans');

        $this->assertSame(['team', 'large'], array_column($plans, 'slug'));
        $this->assertSame(['slug' => 'team', 'platform' => 'hr', 'name' => 'Team', 'amount_minor' => 24900, 'currency' => 'ZAR', 'interval' => 'month', 'trial_days' => 14], $plans[0]);
        $this->assertSame([99900, 'year', null], [$plans[1]['amount_minor'], $plans[1]['interval'], $plans[1]['trial_days']]);
        $this->assertSame([], $response->json('bundles'));
    }

    public function test_it_lists_approved_bundles_with_their_platforms_and_not_drafts(): void
    {
        $key = $this->financeKey();
        $this->bundle(['forms', 'projects']);
        $this->bundle(['hr', 'agents'], false);

        $bundles = $this->read($key)->assertOk()->json('bundles');

        $this->assertCount(1, $bundles);
        $this->assertSame(['name' => 'Office pair', 'amount_minor' => 39900, 'currency' => 'ZAR', 'interval' => 'month'], collect($bundles[0])->only(['name', 'amount_minor', 'currency', 'interval'])->all());
        $this->assertSame(['forms', 'projects'], array_column($bundles[0]['platforms'], 'platform'));
        $this->assertNotSame('', $bundles[0]['slug']);
    }

    public function test_with_nothing_approved_the_answer_is_empty_because_the_application_ships_no_price(): void
    {
        $this->assertSame(['plans' => [], 'bundles' => []], $this->read($this->financeKey())->assertOk()->json());
    }

    public function test_it_says_nothing_about_companies_or_people(): void
    {
        $key = $this->financeKey();
        $team = $this->company('Northwind Freight');
        $this->plan('hr');
        $this->entitlements()->grantComplimentary($team, 'hr', $this->administrator(), null, self::WHY);

        $body = (string) $this->read($key)->assertOk()->getContent();

        $this->assertStringNotContainsString($team->uuid, $body);
        $this->assertStringNotContainsString('Northwind', $body);
        $this->assertStringNotContainsString('@', $body);
    }

    public function test_with_the_secret_unset_everything_is_refused(): void
    {
        config(['platform_subscriptions.confirmation_secret' => null]);

        foreach (['', 'x', Str::random(32)] as $guess) {
            $this->read($guess)->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        }

        config(['platform_subscriptions.confirmation_secret' => '']);
        $this->read('')->assertStatus(401);
    }

    public function test_a_wrong_stale_or_missing_signature_gets_the_same_refusal(): void
    {
        $key = $this->financeKey();

        $this->read($key, null, 'sha256='.str_repeat('0', 64))->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $this->read(Str::random(40))->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $this->read($key, time() - 400)->assertStatus(401);
        $this->read($key, time() + 400)->assertStatus(401);
        $this->getJson('/api/finance/platform-plans')->assertStatus(401);

        // The secret of another service does not sign here: not the seminar callback secret, not a platform's own.
        config(['knowledge.brain_secret' => Str::random(40), 'ecosystem.service_secrets' => ['finance' => Str::random(40)]]);
        $this->read(config('knowledge.brain_secret'))->assertStatus(401);
        $this->read(config('ecosystem.service_secrets.finance'))->assertStatus(401);

        $this->read($key)->assertOk();
    }

    public function test_it_is_read_only_and_is_throttled_like_the_other_finance_callbacks(): void
    {
        $key = $this->financeKey();

        $this->read($key, null, null, 'POST')->assertStatus(405);

        $route = app('router')->getRoutes()->getByName('api.finance.platform-plans');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('throttle:finance-callbacks', $route->gatherMiddleware());
    }

    public function test_the_plans_it_returns_are_exactly_those_a_payment_can_be_confirmed_for(): void
    {
        $key = $this->financeKey();
        $admin = $this->administrator();
        $this->plan('hr', ['name' => 'Team'], true, $admin);
        $this->plan('projects', ['name' => 'Starter'], false, $admin);

        $listed = collect($this->read($key)->json('plans'))->pluck('platform')->all();
        $this->assertSame(['hr'], $listed);
        $this->assertSame(['hr'], PlatformPlan::all()->filter(fn (PlatformPlan $p) => $p->isSellable() && ! $p->isFree())->pluck('platform_key')->all());
    }
}
