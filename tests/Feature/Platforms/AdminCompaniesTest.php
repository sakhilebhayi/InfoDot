<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use Illuminate\Support\Facades\DB;

/**
 * A company's platforms in the admin centre (R-50 section 9): each action is one Entitlements call that leaves an
 * event with the administrator and the reason, and a message for the platform.
 */
class AdminCompaniesTest extends AdminCentreTestCase
{
    public function test_a_company_is_found_by_name_or_owner_code_and_personal_workspaces_never_are(): void
    {
        $admin = $this->administrator();
        $team = $this->company('Northwind Freight');
        $this->company('Southbank Clinic');

        $this->actingAs($admin)->get('/admin/platforms/companies?q=northw')->assertOk()->assertSee('Northwind Freight')->assertDontSee('Southbank Clinic');
        $this->actingAs($admin)->get('/admin/platforms/companies?q='.$team->code)->assertOk()->assertSee('Northwind Freight');
        $this->actingAs($admin)->get('/admin/platforms/companies?q=%25')->assertOk()->assertDontSee('Northwind Freight');
        $this->actingAs($admin)->get('/admin/platforms/companies?q='.urlencode($admin->currentTeam->name))->assertOk()->assertDontSee('href="'.route('admin.platforms.company', $admin->currentTeam).'"', false);

        // Without a search only companies whose platforms changed are listed.
        $this->actingAs($admin)->get('/admin/platforms/companies')->assertOk()->assertDontSee('Northwind Freight');
        $this->entitlements()->grantComplimentary($team, 'hr', $admin, null, self::WHY);
        $this->actingAs($admin)->get('/admin/platforms/companies')->assertOk()->assertSee('Northwind Freight')->assertDontSee('Southbank Clinic');
    }

    public function test_complimentary_access_is_granted_with_a_reason_and_an_optional_last_day(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $url = '/admin/platforms/companies/'.$team->uuid;

        $this->actingAs($admin)->post($url.'/grant', ['platform' => 'hr'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post($url.'/grant', ['platform' => 'hr', 'reason' => self::WHY, 'until' => now()->subDay()->toDateString()])->assertSessionHasErrors('until');
        $this->assertSame(0, PlatformEntitlement::count());

        $until = now()->addDays(30)->toDateString();
        $this->actingAs($admin)->post($url.'/grant', ['platform' => 'hr', 'reason' => 'Pilot customer for the first quarter.', 'until' => $until])->assertRedirect($url.'?platform=hr')->assertSessionHasNoErrors();

        $entitlement = $this->access()->entitlement($team, 'hr');
        $this->assertSame(['active', 'complimentary', $admin->id, $until], [$entitlement->status, $entitlement->source, $entitlement->granted_by, $entitlement->ended_at->toDateString()]);
        $this->assertTrue($this->access()->decideFor($team->owner, $team, 'hr')->allowed);

        $event = PlatformEntitlementEvent::sole();
        $this->assertSame([$admin->id, 'complimentary_granted', 'Pilot customer for the first quarter.'], [$event->actor_id, $event->type, $event->details['note']]);
        $this->assertSame(1, DB::table('entitlement_outbox')->count());

        // Core, own-sign-in and unknown platforms are refused in words.
        $this->actingAs($admin)->post($url.'/grant', ['platform' => 'files', 'reason' => self::WHY])->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post($url.'/grant', ['platform' => 'charts', 'reason' => self::WHY])->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post($url.'/grant', ['platform' => 'nonsense', 'reason' => self::WHY])->assertSessionHasErrors('platform');
        $this->assertSame(1, PlatformEntitlement::count());

        $this->actingAs($admin)->get($url.'?platform=hr')->assertOk()->assertSee('Pilot customer for the first quarter.')->assertSee('Complimentary')->assertSee('Manage Dot.HR');
    }

    public function test_a_trial_is_started_by_days_or_from_a_plan(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $url = '/admin/platforms/companies/'.$team->uuid;
        $plan = $this->plan('projects', ['trial_days' => 21], true, $admin);

        $this->actingAs($admin)->post($url.'/trial', ['platform' => 'hr', 'reason' => self::WHY])->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post($url.'/trial', ['platform' => 'hr', 'days' => 500, 'reason' => self::WHY])->assertSessionHasErrors('days');

        $this->actingAs($admin)->post($url.'/trial', ['platform' => 'hr', 'days' => 10, 'reason' => self::WHY])->assertSessionHasNoErrors();
        $hr = $this->access()->entitlement($team, 'hr');
        $this->assertSame(['trial', 'trial', null], [$hr->status, $hr->source, $hr->plan_id]);
        $this->assertEqualsWithDelta(now()->addDays(10)->timestamp, $hr->trial_ends_at->timestamp, 5);

        $this->actingAs($admin)->post($url.'/trial', ['platform' => 'projects', 'plan' => $plan->id, 'reason' => self::WHY])->assertSessionHasNoErrors();
        $projects = $this->access()->entitlement($team, 'projects');
        $this->assertSame($plan->id, $projects->plan_id);
        $this->assertEqualsWithDelta(now()->addDays(21)->timestamp, $projects->trial_ends_at->timestamp, 5);

        // A plan of another platform is not a way in.
        $this->actingAs($admin)->post($url.'/trial', ['platform' => 'forms', 'plan' => $plan->id, 'reason' => self::WHY])->assertSessionHasErrors('refused');
        $this->assertNull($this->access()->entitlement($team, 'forms'));

        $this->assertSame(['trial_started', 'trial_started'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());
        $this->assertSame([$admin->id], PlatformEntitlementEvent::distinct()->pluck('actor_id')->all());
    }

    public function test_suspend_restore_cancel_and_access_mode_each_leave_an_event_with_the_actor_and_reason(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $owner = $team->owner;
        $url = '/admin/platforms/companies/'.$team->uuid;
        $this->activatePlatforms($team, 'hr');

        $this->actingAs($admin)->post($url.'/suspend', ['platform' => 'hr', 'reason' => 'Chargeback dispute being looked at.'])->assertSessionHasErrors('confirm');
        $this->assertTrue($this->access()->decideFor($owner, $team, 'hr')->allowed);

        $this->actingAs($admin)->post($url.'/suspend', ['platform' => 'hr', 'reason' => 'Chargeback dispute being looked at.', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $this->access()->decideFor($owner, $team, 'hr')->reason);

        $this->actingAs($admin)->post($url.'/access', ['platform' => 'hr', 'mode' => 'selected', 'reason' => 'Asked for by the owner by email.'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url.'/access', ['platform' => 'hr', 'mode' => 'nobody', 'reason' => self::WHY])->assertSessionHasErrors('mode');

        $this->actingAs($admin)->post($url.'/restore', ['platform' => 'hr', 'reason' => 'Dispute settled in the company favour.'])->assertSessionHasNoErrors();
        $this->assertTrue($this->access()->decideFor($owner, $team, 'hr')->allowed);
        $this->assertSame('selected', $this->access()->entitlement($team, 'hr')->access_mode);

        $this->actingAs($admin)->post($url.'/cancel', ['platform' => 'hr', 'reason' => 'Company asked to close the platform.', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->access()->decideFor($owner, $team, 'hr')->reason);

        // Nothing to act on: a refusal in words, not an error page.
        $this->actingAs($admin)->post($url.'/suspend', ['platform' => 'forms', 'reason' => self::WHY, 'confirm' => 1])->assertSessionHasErrors('refused');

        $events = PlatformEntitlementEvent::orderBy('id')->get();
        $this->assertSame(['activated', 'suspended', 'access_mode_changed', 'restored', 'cancelled'], $events->pluck('type')->all());
        $this->assertSame([$owner->id, $admin->id, $admin->id, $admin->id, $admin->id], $events->pluck('actor_id')->all());
        $this->assertSame(['Chargeback dispute being looked at.', 'Asked for by the owner by email.', 'Dispute settled in the company favour.', 'Company asked to close the platform.'], $events->skip(1)->pluck('details.note')->all());
        $this->assertSame(5, DB::table('entitlement_outbox')->count(), 'every change has its message to the platform');

        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Chargeback dispute being looked at.')->assertSee('Company asked to close the platform.')->assertSee('(platform administrator)');
    }

    public function test_the_company_page_shows_the_core_and_what_can_be_done_next(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $this->entitlements()->grantComplimentary($team, 'hr', $admin, null, self::WHY);
        $this->entitlements()->suspend($team, 'hr', $admin, self::WHY);

        $page = $this->actingAs($admin)->get('/admin/platforms/companies/'.$team->uuid.'?platform=hr')->assertOk();
        $page->assertSee('The free core')->assertSee('Restore Dot.HR')->assertDontSee('Suspend Dot.HR')->assertSee('Grant complimentary access')->assertSee('Start the trial');

        // A platform the company does not hold cannot be selected for managing.
        $this->actingAs($admin)->get('/admin/platforms/companies/'.$team->uuid.'?platform=forms')->assertOk()->assertDontSee('id="manage"', false);
    }
}
