<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\Team;
use App\Models\UserNotice;
use App\Services\Platforms\EntitlementRefused;
use Illuminate\Support\Facades\DB;

/**
 * Starting a trial from the catalogue (R-50 section 6): only where an approved, priced plan offers trial days, only by
 * the company's owner or an administrator, once per company and platform, and only after a page that states the end
 * date and that nothing is charged when the trial ends. The plans here are made the way a platform administrator makes
 * them; the application itself ships none.
 */
class PlatformTrialFromCatalogueTest extends AdminCentreTestCase
{
    private function entitlement(Team $team, string $key = 'hr'): ?PlatformEntitlement
    {
        return PlatformEntitlement::where('team_id', $team->id)->where('platform_key', $key)->first();
    }

    public function test_the_platform_page_offers_the_trial_to_the_owner_with_its_length(): void
    {
        $team = $this->company('Northwind Freight');
        $this->plan('hr', ['trial_days' => 14]);

        $page = $this->actingAs($team->owner)->get('/platforms/hr')->assertOk();

        $page->assertSee('Trial available')
            ->assertSee('data-platform-action="start_trial"', false)
            ->assertSee('Start the 14-day trial')
            ->assertSee(route('platforms.trial.confirm', ['platform' => 'hr']), false)
            ->assertSee('A trial costs nothing.')
            ->assertSee('nothing is charged, and nothing is deleted');

        $this->actingAs($team->owner)->get('/platforms')->assertOk()->assertSee('data-platform-action="start_trial"', false);
        $this->assertNull($this->entitlement($team), 'reading the page starts nothing');
    }

    public function test_the_confirmation_states_the_end_date_and_that_nothing_is_charged_at_the_end(): void
    {
        $this->travelTo('2026-11-14 09:00:00');
        $team = $this->company('Northwind Freight');
        $this->plan('hr', ['name' => 'Standard', 'trial_days' => 14]);

        $page = $this->actingAs($team->owner)->get('/platforms/hr/trial')->assertOk();

        $page->assertSee('Start a 14-day trial of Dot.HR for Northwind Freight?')
            ->assertSee('The trial ends on')
            ->assertSee('28 November 2026')
            ->assertSee('Nothing is charged, now or when the trial ends.')
            ->assertSee('No payment details are asked for.')
            ->assertSee('access simply ends, unless Northwind Freight chooses a paid plan later')
            ->assertSee('Nothing is deleted when the trial ends.')
            ->assertSee('can have one trial of Dot.HR')
            // The only amount on the page is the one the administrator approved.
            ->assertSee('ZAR 249.00 a month');

        // A person reads "company", never "team".
        $html = (string) $page->getContent();
        $main = substr($html, (int) strpos($html, '<main'), (int) strpos($html, '</main>') - (int) strpos($html, '<main'));
        $this->assertDoesNotMatchRegularExpression('/\bteams?\b/i', strip_tags($main));
        $this->assertNull($this->entitlement($team), 'the page alone starts nothing');
    }

    public function test_the_owner_starts_the_trial_and_everyone_in_the_company_can_open_the_platform(): void
    {
        $this->travelTo('2026-11-14 09:00:00');
        $team = $this->company('Northwind Freight');
        $member = $this->memberOf($team);
        $admin = $this->memberOf($team, 'admin');
        $plan = $this->plan('hr', ['trial_days' => 14]);

        $this->actingAs($team->owner)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => $plan->slug])
            ->assertRedirect(route('platforms.show', ['platform' => 'hr']))
            ->assertSessionHas('status', 'The trial of Dot.HR has started for Northwind Freight. It ends on 28 November 2026, and nothing is charged when it does.');

        $entitlement = $this->entitlement($team);
        $this->assertSame(['trial', 'trial', $plan->id, $team->owner->id], [$entitlement->status, $entitlement->source, $entitlement->plan_id, $entitlement->granted_by]);
        $this->assertSame('2026-11-28', $entitlement->trial_ends_at->toDateString());
        $this->assertNull($entitlement->reference, 'no payment is attached to a trial');
        $this->assertTrue($this->access()->decideFor($member, $team, 'hr')->allowed);

        $event = PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', 'hr')->sole();
        $this->assertSame(['trial_started', $team->owner->id], [$event->type, $event->actor_id]);
        $this->assertSame(1, DB::table('entitlement_outbox')->where('platform_key', 'hr')->count(), 'the platform is told once');
        $this->assertSame(0, DB::table('platform_payment_confirmations')->count(), 'a trial is not a payment');

        // The administrator is told; the owner, who did it, is not.
        $this->assertSame([$admin->id], UserNotice::where('kind', UserNotice::PLATFORM_ENTITLEMENT)->pluck('user_id')->all());

        $this->actingAs($member)->get('/platforms/hr')->assertOk()->assertSee('Northwind Freight is trying Dot.HR until 28 November 2026.')
            ->assertSee('data-platform-action="open"', false)->assertDontSee('data-platform-action="start_trial"', false);
    }

    public function test_an_administrator_of_the_company_may_start_the_trial_too(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $plan = $this->plan('hr', ['trial_days' => 7]);

        $this->actingAs($admin)->get('/platforms/hr/trial')->assertOk()->assertSee('Start a 7-day trial');
        $this->actingAs($admin)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => $plan->slug])->assertRedirect();

        $this->assertSame(['trial', $admin->id], [$this->entitlement($team)->status, $this->entitlement($team)->granted_by]);
    }

    public function test_a_member_and_a_manager_see_the_offer_but_cannot_start_it(): void
    {
        $team = $this->company('Northwind Freight');
        $plan = $this->plan('hr', ['trial_days' => 14]);

        foreach ([$this->memberOf($team), $this->memberOf($team, 'manager')] as $person) {
            $this->actingAs($person)->get('/platforms/hr')->assertOk()
                ->assertSee('Trial available')->assertSee('Ask '.$team->owner->name)->assertSee('to start it')
                ->assertDontSee('data-platform-action="start_trial"', false);
            $this->actingAs($person)->get('/platforms/hr/trial')->assertForbidden();
            $this->actingAs($person)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => $plan->slug])->assertForbidden();
        }

        $this->assertNull($this->entitlement($team));
        $this->assertSame(0, PlatformEntitlementEvent::count());
    }

    public function test_a_personal_workspace_a_signed_out_visitor_and_an_outsider_cannot_start_one(): void
    {
        $team = $this->company('Northwind Freight');
        $other = $this->company('Kestrel Mining');
        $plan = $this->plan('hr', ['trial_days' => 14]);
        $form = ['company' => $team->uuid, 'plan' => $plan->slug];

        $this->get('/platforms/hr/trial')->assertRedirect('/login');
        $this->post('/platforms/hr/trial', $form)->assertRedirect('/login');

        $alone = $this->person();
        $this->actingAs($alone)->get('/platforms/hr/trial')->assertForbidden();
        $this->actingAs($alone)->post('/platforms/hr/trial', ['company' => $alone->currentTeam->uuid, 'plan' => $plan->slug])->assertForbidden();

        // The owner of another company, naming this one: refused, and nothing is written for either.
        $this->actingAs($other->owner)->post('/platforms/hr/trial', $form)->assertStatus(409);
        // A platform administrator and a moderator have no company role here; the catalogue gives them nothing extra.
        foreach ([$this->administrator(), $this->moderator()] as $staff) {
            $this->actingAs($staff)->post('/platforms/hr/trial', $form)->assertStatus(409);
        }

        $this->assertSame(0, PlatformEntitlement::count());
        $this->assertSame(0, PlatformEntitlementEvent::count());
    }

    public function test_a_company_has_one_trial_of_a_platform_however_it_ended_and_whoever_started_it(): void
    {
        $team = $this->company('Northwind Freight');
        $owner = $team->owner;
        $plan = $this->plan('hr', ['trial_days' => 14]);
        $form = ['company' => $team->uuid, 'plan' => $plan->slug];

        $this->actingAs($owner)->post('/platforms/hr/trial', $form)->assertRedirect();

        // A second press while it runs changes nothing.
        $this->actingAs($owner)->post('/platforms/hr/trial', $form)->assertSessionHas('status');
        $this->assertSame(1, PlatformEntitlementEvent::where('type', 'trial_started')->count());

        // It runs out. Nothing is charged; the page says the trial ended and offers no second one.
        $this->travel(15)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertSame('payment_required', $this->entitlement($team)->status);
        $this->assertSame('trial_ended', $this->access()->businessState($team, 'hr'));
        $this->assertFalse($this->access()->decideFor($owner, $team, 'hr')->allowed);
        $this->assertSame(0, DB::table('platform_payment_confirmations')->count());

        $this->actingAs($owner)->get('/platforms/hr')->assertOk()->assertSee('Trial ended')->assertSee('Nothing was charged and nothing was deleted')
            ->assertDontSee('data-platform-action="start_trial"', false);
        $this->actingAs($owner)->get('/platforms/hr/trial')->assertRedirect(route('platforms.show', ['platform' => 'hr']));
        $this->actingAs($owner)->post('/platforms/hr/trial', $form)->assertSessionHas('status', 'Dot.HR has no trial that Northwind Freight can start. Nothing was changed and nothing was charged.');
        $this->assertSame(1, PlatformEntitlementEvent::where('type', 'trial_started')->count());

        // The service itself refuses a second one, whatever the page does.
        try {
            $this->entitlements()->startTrial($team, 'hr', $owner, $plan);
            $this->fail('A second trial started.');
        } catch (EntitlementRefused $e) {
            $this->assertSame('The company has already had its trial of this platform.', $e->getMessage());
        }

        // A trial that InfoDot gave counts as the company's one trial as well, even after it was cancelled.
        $second = $this->company('Kestrel Mining');
        $administrator = $this->administrator();
        $this->entitlements()->startTrial($second, 'hr', $administrator, null, 5, self::WHY);
        $this->entitlements()->cancel($second, 'hr', $administrator, self::WHY);

        $this->assertSame('available_for_purchase', $this->access()->businessState($second, 'hr'));
        $this->actingAs($second->owner)->get('/platforms/hr')->assertOk()->assertDontSee('data-platform-action="start_trial"', false);
        $this->actingAs($second->owner)->post('/platforms/hr/trial', ['company' => $second->uuid, 'plan' => $plan->slug])->assertSessionHas('status');
        $this->assertSame('cancelled', $this->entitlement($second)->status);
    }

    public function test_no_trial_is_offered_without_an_approved_priced_plan_that_has_trial_days(): void
    {
        $team = $this->company();
        $owner = $team->owner;

        $this->plan('hr');                                             // approved, but no trial days
        $draft = $this->plan('projects', ['trial_days' => 14], false); // trial days, but the price is not approved
        $this->plan('design', ['trial_days' => 14, 'active' => false]); // approved, but not on sale

        foreach (['hr' => 'available_for_purchase', 'projects' => 'free_while_pricing_finalised', 'design' => 'free_while_pricing_finalised', 'forms' => 'free_while_pricing_finalised'] as $key => $state) {
            $this->assertSame($state, $this->access()->businessState($team, $key), $key);
            $this->actingAs($owner)->get("/platforms/{$key}")->assertOk()->assertDontSee('data-platform-action="start_trial"', false);
            $this->actingAs($owner)->get("/platforms/{$key}/trial")->assertRedirect(route('platforms.show', ['platform' => $key]));
        }

        // A plan that is not on sale cannot be named in a forged form either.
        $this->actingAs($owner)->post('/platforms/projects/trial', ['company' => $team->uuid, 'plan' => $draft->slug])->assertSessionHas('status');
        // Nor a plan of another platform, nor the core, nor a platform with its own sign-in.
        $trial = $this->plan('agents', ['trial_days' => 14]);
        $this->actingAs($owner)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => $trial->slug])->assertSessionHas('status');
        $this->actingAs($owner)->post('/platforms/files/trial', ['company' => $team->uuid, 'plan' => $trial->slug])->assertSessionHas('status');
        $this->actingAs($owner)->post('/platforms/mines/trial', ['company' => $team->uuid, 'plan' => $trial->slug])->assertSessionHas('status');

        $this->assertSame(0, PlatformEntitlement::count());
        $this->assertSame(0, PlatformEntitlementEvent::count());
    }

    public function test_with_two_trial_plans_the_page_is_about_the_one_chosen_and_names_the_other(): void
    {
        $this->travelTo('2026-11-14 09:00:00');
        $team = $this->company();
        $short = $this->plan('hr', ['name' => 'Starter', 'amount_minor' => 9900, 'trial_days' => 7]);
        $long = $this->plan('hr', ['name' => 'Team', 'trial_days' => 30]);

        $this->actingAs($team->owner)->get('/platforms/hr/trial')->assertOk()
            ->assertSee('Start a 7-day trial')->assertSee('21 November 2026')->assertSee('Team, 30 days');
        $this->actingAs($team->owner)->get('/platforms/hr/trial?plan='.$long->slug)->assertOk()
            ->assertSee('Start a 30-day trial')->assertSee('14 December 2026')->assertSee('Starter, 7 days');

        $this->actingAs($team->owner)->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => $long->slug])->assertRedirect();
        $this->assertSame([$long->id, '2026-12-14'], [$this->entitlement($team)->plan_id, $this->entitlement($team)->trial_ends_at->toDateString()]);
        $this->assertNotSame($short->id, $this->entitlement($team)->plan_id);
    }

    public function test_starting_a_trial_needs_the_csrf_token_and_a_post(): void
    {
        $team = $this->company();
        $plan = $this->plan('hr', ['trial_days' => 14]);

        // A link (GET) only ever shows the page.
        $this->actingAs($team->owner)->get('/platforms/hr/trial?company='.$team->uuid.'&plan='.$plan->slug)->assertOk();
        $this->assertNull($this->entitlement($team));

        // CSRF verification is skipped while tests run: run the request in production mode, where the middleware is active.
        $this->app['env'] = 'production';
        $this->actingAs($team->owner)->withSession(['_token' => 'the-real-one'])->post('/platforms/hr/trial', ['company' => $team->uuid, 'plan' => $plan->slug, '_token' => 'a-guess'])->assertStatus(419);
        $this->assertNull($this->entitlement($team));
    }
}
