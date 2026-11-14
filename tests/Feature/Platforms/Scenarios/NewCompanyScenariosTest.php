<?php

namespace Tests\Feature\Platforms\Scenarios;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Services\Platforms\AccessDecision;
use Illuminate\Support\Facades\DB;

/** Brief section 15, scenarios 1, 2, 4, 11 and 17: what a company starts with, and how it adds to it for free. */
class NewCompanyScenariosTest extends ScenarioTestCase
{
    public function test_scenario_01_a_newly_registered_business_with_no_optional_platforms_activated(): void
    {
        $team = $this->company('Northwind Freight');
        $owner = $team->owner;
        $member = $this->memberOf($team);

        // Only the free core opens, for the owner and for everyone else in the company, with nothing switched on.
        foreach (['files', 'finance'] as $core) {
            $this->assertCanOpen($owner, $core);
            $this->assertCanOpen($member, $core);
        }

        foreach (['forms', 'hr', 'projects', 'agents'] as $optional) {
            $this->assertCannotOpen($owner, $optional, AccessDecision::NOT_ACTIVATED);
            $this->assertCannotOpen($member, $optional, AccessDecision::NOT_ACTIVATED);
        }

        $this->assertSame(0, PlatformEntitlement::count(), 'the core is held by every company without a record');

        // What Platforms and Analytics show says the same: four included, the rest to switch on.
        $page = $this->actingAs($owner)->get('/platforms')->assertOk();
        $page->assertSee('Free while pricing is being finalised');
        $analytics = (string) $this->actingAs($owner)->get('/analytics')->assertOk()->assertSee('Your company&#039;s platforms', false)->getContent();
        $this->assertMatchesRegularExpression('/4 of the \d+ in the catalogue/', $analytics);
    }

    public function test_scenario_02_a_business_activating_a_free_platform(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);

        // The owner reads what will happen, then confirms.
        $this->actingAs($team->owner)->get('/platforms/forms/switch-on')->assertOk()->assertSee('No charge will ever be made without new, explicit consent');
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect(route('platforms.show', ['platform' => 'forms']))->assertSessionHas('status');

        $entitlement = $this->held($team, 'forms');
        $this->assertSame(['active', 'unpriced_free', 'everyone'], [$entitlement->status, $entitlement->source, $entitlement->access_mode]);
        $this->assertCanOpen($team->owner, 'forms');
        $this->assertCanOpen($employee, 'forms');
        $this->assertSame(['activated'], $this->history($team, 'forms'));
        $this->assertTrue($this->lastTold($team, 'forms')['entitled'], 'the platform is told, once');
        $this->assertSame(0, DB::table('platform_payment_confirmations')->count());

        // A platform with an approved free plan is "free", not "free while pricing is being finalised".
        $this->plan('sheet', ['name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'interval' => null]);
        $this->switchOn($team, $team->owner, 'sheet')->assertRedirect();
        $this->assertSame('free', $this->held($team, 'sheet')->source);
    }

    public function test_scenario_04_a_business_independently_activating_multiple_platforms(): void
    {
        $team = $this->company('Northwind Freight');
        $member = $this->memberOf($team);
        $admin = $this->memberOf($team, 'admin');

        // The owner and an administrator each switch some on.
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->switchOn($team, $admin, 'projects')->assertRedirect();
        $this->switchOn($team, $team->owner, 'sheet')->assertRedirect();

        foreach (['forms', 'projects', 'sheet'] as $key) {
            $this->assertCanOpen($member, $key);
            $this->assertSame('active', $this->held($team, $key)->status);
        }
        $this->assertCannotOpen($member, 'hr', AccessDecision::NOT_ACTIVATED);

        // Each is its own entitlement: switching one off touches only that one.
        $this->actingAs($team->owner)->post('/platforms/projects/switch-off', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame(['active', 'cancelled', 'active'], [$this->held($team, 'forms')->status, $this->held($team, 'projects')->status, $this->held($team, 'sheet')->status]);
        $this->assertCannotOpen($member, 'projects', AccessDecision::CANCELLED);
        $this->assertCanOpen($member, 'forms');
        $this->assertSame(['activated'], $this->history($team, 'forms'), 'forms was not touched');

        // Pressing the button twice makes one entitlement and one entry in the history.
        $this->switchOn($team, $team->owner, 'forms');
        $this->assertSame(1, PlatformEntitlement::where('team_id', $team->id)->where('platform_key', 'forms')->count());
        $this->assertSame(1, PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', 'forms')->count());
    }

    public function test_scenario_11_a_business_activating_an_application_without_completing_unrelated_milestones(): void
    {
        // A company that has done nothing at all in InfoDot: no business page, no question, no solution, no teammate, no profile text.
        $team = $this->company('Brand New Co');
        $this->assertSame(0, DB::table('questions')->where('team_id', $team->id)->count());
        $this->assertSame(0, DB::table('solutions')->where('team_id', $team->id)->count());
        $this->assertSame(0, DB::table('business_services')->where('team_id', $team->id)->count());
        $this->assertSame(0, $team->users()->count());

        $this->switchOn($team, $team->owner, 'forms')->assertRedirect()->assertSessionHas('status');
        $this->switchOn($team, $team->owner, 'hr')->assertRedirect()->assertSessionHas('status');

        $this->assertCanOpen($team->owner, 'forms');
        $this->assertCanOpen($team->owner, 'hr');

        // And nothing in the way of the page asks for one: the confirmation lists what happens and nothing about getting started.
        $this->actingAs($team->owner)->get('/platforms/agents/switch-on')->assertOk()->assertDontSee('first steps', false)->assertDontSee('complete your');
    }

    public function test_scenario_17_a_business_attempting_to_access_an_operational_but_unactivated_platform(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);

        $this->assertSame('operational', DB::table('platform_catalogue')->where('key', 'hr')->value('operational_status'));

        // The launch is refused with the reason, not with an outage page, and no launch code is made.
        $this->open($employee, 'hr')->assertForbidden()->assertSee('Northwind Freight has not switched on Dot.HR')->assertSee('Ask '.$team->owner->name)->assertDontSee('cannot be opened right now');
        $this->open($team->owner, 'hr')->assertForbidden()->assertSee('Switch on Dot.HR');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        // Arriving at the platform with a code from another door is refused too: it says no company is entitled.
        $this->redeemFor($team->owner, 'hr')->assertForbidden();
        $this->assertSame(0, DB::table('personal_access_tokens')->whereRaw("name = 'ecosystem-handoff:hr'")->count(), 'the code is spent either way');

        // The platform's own read says the same about the company.
        $row = collect($this->withHeaders(['Authorization' => 'Bearer '.config('ecosystem.service_secrets.hr')])->getJson('/api/ecosystem/companies/'.$team->uuid.'/platforms?platform=hr')->assertOk()->json('platforms'))->firstWhere('key', 'hr');
        $this->assertFalse($row['entitled']);
    }
}
