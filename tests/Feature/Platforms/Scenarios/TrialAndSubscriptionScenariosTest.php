<?php

namespace Tests\Feature\Platforms\Scenarios;

use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Services\Platforms\AccessDecision;
use Illuminate\Support\Facades\DB;

/**
 * Brief section 15, scenarios 3, 5, 6, 7, 8, 13, 14 and 19: trials and paid subscriptions. A trial involves no money
 * and is proved whole. Everything paid is proved on InfoDot's side only, from the confirmation Dot.Finance will send
 * (signed here by the test), and ends in markTestIncomplete because Dot.Finance cannot take a payment yet.
 */
class TrialAndSubscriptionScenariosTest extends ScenarioTestCase
{
    public function test_scenario_03_a_business_subscribing_to_a_paid_platform(): void
    {
        $team = $this->company('Northwind Freight');
        $plan = $this->plan('hr');

        // With a price approved, the platform cannot be switched on for free, and the page says why. Nothing is charged.
        $this->actingAs($team->owner)->get('/platforms/hr')->assertOk()->assertSee('Available for purchase')->assertSee('Paid activation is not open yet.')->assertSee('ZAR 249.00 a month');
        $this->switchOn($team, $team->owner, 'hr')->assertRedirect()->assertSessionHas('status');
        $this->assertNull($this->held($team, 'hr'));

        // The owner asks for the paid plan: it waits, and nothing opens.
        $this->entitlements()->subscribe($team, 'hr', $plan, $team->owner);
        $this->assertSame('pending', $this->held($team, 'hr')->status);
        $this->assertCannotOpen($team->owner, 'hr', AccessDecision::PAYMENT_REQUIRED);
        $this->actingAs($team->owner)->get('/platforms/hr')->assertSee('Activation pending');

        // Only the signed confirmation opens it.
        $this->deliver($this->paymentEvent($team), $this->financeKey())->assertOk()->assertExactJson(['status' => 'applied']);
        $this->assertSame(['active', 'paid'], [$this->held($team, 'hr')->status, $this->held($team, 'hr')->source]);
        $this->assertCanOpen($team->owner, 'hr');

        $this->markTestIncomplete(self::PAYMENT_NOT_BUILT);
    }

    public function test_scenario_05_a_business_starting_a_trial(): void
    {
        $this->travelTo('2026-11-14 09:00:00');
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->plan('hr', ['trial_days' => 14]);

        $this->actingAs($team->owner)->get('/platforms/hr/trial')->assertOk()->assertSee('28 November 2026')->assertSee('Nothing is charged, now or when the trial ends.');
        $this->actingAs($team->owner)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => 'team'])->assertRedirect();

        $this->assertSame(['trial', 'trial'], [$this->held($team, 'hr')->status, $this->held($team, 'hr')->source]);
        $this->assertCanOpen($employee, 'hr');
        $this->assertSame('trial_started', $this->history($team, 'hr')[0]);
        $this->assertSame('trial', $this->lastTold($team, 'hr')['status']);

        // It ends without a charge: access ends, nothing is deleted, and there is no second trial.
        $this->travel(15)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertCannotOpen($employee, 'hr', AccessDecision::TRIAL_ENDED);
        $this->assertNotNull($this->held($team, 'hr'));
        $this->assertSame(0, DB::table('platform_payment_confirmations')->count());
        $this->actingAs($team->owner)->get('/platforms/hr')->assertSee('Trial ended')->assertDontSee('data-platform-action="start_trial"', false);
        $this->actingAs($team->owner)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => 'team'])->assertSessionHas('status');
        $this->assertSame(1, collect($this->history($team, 'hr'))->filter(fn ($type) => $type === 'trial_started')->count());
    }

    public function test_scenario_06_a_business_with_an_expired_subscription(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->plan('hr');
        $key = $this->financeKey();
        $this->paidSubscription($team, 'hr', $key);
        $this->assertCanOpen($employee, 'hr');

        // Payment fails, a grace period runs, and after it the subscription is expired.
        $this->deliver($this->paymentEvent($team, 'payment_failed'), $key)->assertOk();
        $this->assertCanOpen($employee, 'hr', 'access continues through the grace period');
        $this->travel(8)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();

        $this->assertSame('expired', $this->held($team, 'hr')->status);
        $this->assertCannotOpen($employee, 'hr', AccessDecision::EXPIRED);
        $this->actingAs($team->owner)->get('/platforms/hr')->assertSee('Subscription expired')->assertSee('Nothing was deleted')->assertSee('data-platform-action="finance"', false);
        $this->assertFalse($this->lastTold($team, 'hr')['entitled']);
        $this->assertNotNull($this->held($team, 'hr'), 'the record and its history stay');

        $this->markTestIncomplete(self::PAYMENT_NOT_BUILT);
    }

    public function test_scenario_07_a_business_upgrading_its_subscription(): void
    {
        $team = $this->company('Northwind Freight');
        $small = $this->plan('hr', ['name' => 'Team']);
        $large = $this->plan('hr', ['name' => 'Large', 'amount_minor' => 49900]);
        $key = $this->financeKey();

        $this->entitlements()->subscribe($team, 'hr', $small, $team->owner);
        $this->deliver($this->paymentEvent($team, 'paid', ['plan' => $small->slug]), $key)->assertOk();
        $this->assertSame($small->id, $this->held($team, 'hr')->plan_id);

        // The upgrade is a new paid confirmation for the larger plan. The plan changes, access is unbroken, the history keeps both.
        $this->deliver($this->paymentEvent($team, 'paid', ['plan' => $large->slug, 'amount_minor' => 49900, 'reference' => 'SUB-1002']), $key)->assertOk()->assertExactJson(['status' => 'applied']);

        $this->assertSame([$large->id, 'active'], [$this->held($team, 'hr')->plan_id, $this->held($team, 'hr')->status]);
        $this->assertCanOpen($team->owner, 'hr');
        $details = json_decode((string) DB::table('platform_entitlement_events')->where('team_id', $team->id)->where('type', 'payment_confirmed')->orderByDesc('id')->value('details'), true);
        $this->assertSame('team', $details['plan_changed_from']);
        $this->assertSame(2, DB::table('platform_payment_confirmations')->where('team_id', $team->id)->where('outcome', 'applied')->count());

        $this->markTestIncomplete(self::PAYMENT_NOT_BUILT.' The owner\'s upgrade checkout does not exist either.');
    }

    public function test_scenario_08_a_business_cancelling_a_subscription(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->plan('hr');
        $this->paidSubscription($team);
        $periodEnd = $this->held($team, 'hr')->current_period_ends_at;

        // The owner cancels. Time already paid for is honoured; nothing is deleted.
        $this->entitlements()->cancel($team, 'hr', $team->owner);
        $this->assertSame('cancelled', $this->held($team, 'hr')->status);
        $this->assertCanOpen($employee, 'hr', 'the paid period is honoured');
        $this->actingAs($team->owner)->get('/platforms/hr')->assertSee('stays open until');

        $this->travelTo($periodEnd->copy()->addMinute());
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertCannotOpen($employee, 'hr', AccessDecision::CANCELLED);
        $this->assertSame(['subscription_requested', 'payment_confirmed', 'cancelled', 'period_ended'], $this->history($team, 'hr'));
        $this->assertNotNull($this->held($team, 'hr'));

        // A company that switched a free platform on cancels it from the page, owner only.
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->actingAs($employee)->post('/platforms/forms/switch-off', ['company' => $team->uuid])->assertForbidden();
        $this->actingAs($team->owner)->post('/platforms/forms/switch-off', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame('cancelled', $this->held($team, 'forms')->status);

        $this->markTestIncomplete('The owner has no page to cancel a paid plan: it is done in Dot.Finance, which has no subscription pages yet. The cancel itself is proved at the service.');
    }

    public function test_scenario_13_a_business_accessing_a_platform_after_verified_payment(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->plan('hr');
        $key = $this->financeKey();
        $this->entitlements()->subscribe($team, 'hr', PlatformPlan::where('platform_key', 'hr')->firstOrFail(), $team->owner);
        $this->assertCannotOpen($employee, 'hr', AccessDecision::PAYMENT_REQUIRED);

        // A confirmation with a wrong signature changes nothing; the right one opens the platform for everyone.
        $this->deliver($this->paymentEvent($team), $key, null, 'sha256='.str_repeat('0', 64))->assertStatus(401);
        $this->assertCannotOpen($employee, 'hr', AccessDecision::PAYMENT_REQUIRED);

        $this->deliver($this->paymentEvent($team), $key)->assertOk();
        $this->assertCanOpen($employee, 'hr');
        $this->assertCanOpen($team->owner, 'hr');
        $this->assertTrue($this->lastTold($team, 'hr')['entitled']);
        $this->assertSame('paid', $this->lastTold($team, 'hr')['source']);

        // The platform that is told the person has arrived sees the company as entitled.
        $this->assertTrue(collect($this->redeemFor($employee, 'hr')->assertOk()->json('teams'))->firstWhere('uuid', $team->uuid)['entitled']);

        $this->markTestIncomplete(self::PAYMENT_NOT_BUILT);
    }

    public function test_scenario_14_a_business_experiencing_a_failed_payment(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->plan('hr');
        $key = $this->financeKey();
        $this->paidSubscription($team, 'hr', $key);

        $this->deliver($this->paymentEvent($team, 'payment_failed'), $key)->assertOk()->assertExactJson(['status' => 'applied']);

        $entitlement = $this->held($team, 'hr');
        $this->assertSame('payment_required', $entitlement->status);
        $this->assertNotNull($entitlement->grace_until);
        $this->assertCanOpen($employee, 'hr', 'the company keeps the platform through the grace period');
        $this->assertSame('payment_required', $this->lastTold($team, 'hr')['status']);

        // The owner is told, and is pointed at Dot.Finance; nothing was deleted at any point.
        $this->actingAs($team->owner)->get(route('notices'))->assertOk()->assertSee('did not go through');
        $this->actingAs($team->owner)->get('/platforms/hr')->assertSee('Payment needed');

        // A second failure does not move the date, and a later payment restores it.
        $until = $entitlement->grace_until->toIso8601String();
        $this->deliver($this->paymentEvent($team, 'payment_failed'), $key)->assertOk();
        $this->assertSame($until, $this->held($team, 'hr')->grace_until->toIso8601String());
        $this->deliver($this->paymentEvent($team, 'paid', ['occurred_at' => now()->addMinute()->toIso8601String()]), $key)->assertOk();
        $this->assertSame(['active', null], [$this->held($team, 'hr')->status, $this->held($team, 'hr')->grace_until]);

        $this->markTestIncomplete(self::PAYMENT_NOT_BUILT);
    }

    public function test_scenario_19_a_business_renewing_an_expired_subscription(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->plan('hr');
        $key = $this->financeKey();
        $this->paidSubscription($team, 'hr', $key);
        $this->deliver($this->paymentEvent($team, 'payment_failed'), $key)->assertOk();
        $this->travel(8)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertCannotOpen($employee, 'hr', AccessDecision::EXPIRED);

        // A confirmed payment after the expiry brings it back, with a new period, and nothing was lost meanwhile.
        $this->deliver($this->paymentEvent($team, 'paid', ['reference' => 'SUB-1003', 'occurred_at' => now()->toIso8601String()]), $key)->assertOk()->assertExactJson(['status' => 'applied']);

        $entitlement = $this->held($team, 'hr');
        $this->assertSame(['active', 'SUB-1003', null], [$entitlement->status, $entitlement->reference, $entitlement->ended_at]);
        $this->assertCanOpen($employee, 'hr');
        $this->assertTrue($this->lastTold($team, 'hr')['entitled']);
        $this->assertSame(1, PlatformEntitlement::where('team_id', $team->id)->where('platform_key', 'hr')->count(), 'the same entitlement, not a new one');

        $this->markTestIncomplete(self::PAYMENT_NOT_BUILT);
    }
}
