<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformBundle;
use App\Models\PlatformCatalogue;
use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Services\Platforms\CatalogueAdmin;
use App\Services\Platforms\EntitlementRefused;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The catalogue, plans and bundles in the admin centre (R-50 section 9): every change is audited with who and why,
 * the core flag is read-only, and a price is sold only after an administrator approved that exact price.
 */
class AdminCatalogueTest extends AdminCentreTestCase
{
    public function test_a_platform_change_is_saved_and_audited_with_who_and_why(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr', [
            'operational_status' => 'maintenance', 'discoverable' => 0, 'tagline' => 'People, leave and records', 'reason' => 'Database upgrade tonight, back by six.',
        ])->assertRedirect('/admin/platforms/catalogue/hr')->assertSessionHasNoErrors();

        $entry = PlatformCatalogue::where('key', 'hr')->firstOrFail();
        $this->assertSame(['maintenance', false, 'People, leave and records'], [$entry->operational_status, $entry->discoverable, $entry->tagline]);

        $event = PlatformCatalogueEvent::where('subject_type', 'platform')->sole();
        $this->assertSame([$admin->id, 'hr', 'platform_updated', 'Database upgrade tonight, back by six.'], [$event->actor_id, $event->subject_key, $event->type, $event->reason]);
        $this->assertSame('operational', $event->before['operational_status']);
        $this->assertSame('maintenance', $event->after['operational_status']);
        $this->assertArrayHasKey('discoverable', $event->after);

        $this->actingAs($admin)->get('/admin/platforms/catalogue/hr')->assertOk()->assertSee('Database upgrade tonight, back by six.')->assertSee($admin->name);
        $this->actingAs($admin)->get('/admin/platforms/audit')->assertOk()->assertSee('Database upgrade tonight, back by six.');
    }

    public function test_a_change_without_a_reason_is_refused_and_saving_nothing_new_writes_nothing(): void
    {
        $admin = $this->administrator();

        foreach (['', 'short'] as $reason) {
            $this->actingAs($admin)->post('/admin/platforms/catalogue/hr', ['operational_status' => 'unavailable', 'discoverable' => 1, 'reason' => $reason])->assertSessionHasErrors('reason');
        }

        $entry = PlatformCatalogue::where('key', 'hr')->firstOrFail();
        $this->assertSame('operational', $entry->operational_status);

        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr', [
            'operational_status' => 'operational', 'discoverable' => 1, 'tagline' => $entry->tagline, 'description' => $entry->description,
            'benefit' => $entry->benefit, 'audience' => $entry->audience, 'reason' => self::WHY,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, PlatformCatalogueEvent::count());

        try {
            app(CatalogueAdmin::class)->updatePlatform($entry, ['operational_status' => 'unavailable'], $admin, '   ');
            $this->fail('A change with a blank reason went through the service.');
        } catch (EntitlementRefused) {
            $this->assertSame('operational', $entry->fresh()->operational_status);
        }
    }

    public function test_the_core_flag_is_read_only_and_a_core_platform_cannot_be_hidden(): void
    {
        $admin = $this->administrator();

        $this->assertSame(['docs', 'files', 'finance', 'infodot', 'tasks'], PlatformCatalogue::where('is_core', true)->orderBy('key')->pluck('key')->all());

        // Sending the flag does nothing, in either direction.
        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr', ['operational_status' => 'operational', 'discoverable' => 1, 'is_core' => 1, 'reason' => self::WHY]);
        $this->actingAs($admin)->post('/admin/platforms/catalogue/files', ['operational_status' => 'degraded', 'discoverable' => 0, 'is_core' => 0, 'reason' => self::WHY])->assertSessionHasNoErrors();

        $this->assertFalse(PlatformCatalogue::where('key', 'hr')->value('is_core'));
        $files = PlatformCatalogue::where('key', 'files')->firstOrFail();
        $this->assertSame([true, true, 'degraded'], [$files->is_core, $files->discoverable, $files->operational_status]);

        $this->expectException(EntitlementRefused::class);
        app(CatalogueAdmin::class)->updatePlatform($files, ['discoverable' => false], $admin, self::WHY);
    }

    public function test_the_service_refuses_a_moderator_and_an_owner_whatever_calls_it(): void
    {
        $catalogue = app(CatalogueAdmin::class);
        $entry = PlatformCatalogue::where('key', 'hr')->firstOrFail();
        $plan = $this->plan('hr', [], false);
        $refused = 0;

        foreach ([$this->moderator(), $this->company()->owner] as $person) {
            foreach ([
                fn () => $catalogue->updatePlatform($entry, ['operational_status' => 'unavailable'], $person, self::WHY),
                fn () => $catalogue->createPlan('forms', ['name' => 'Sneaky', 'model' => 'subscription', 'amount_minor' => 100, 'currency' => 'ZAR'], $person, self::WHY),
                fn () => $catalogue->updatePlan($plan, ['name' => 'Renamed'], $person, self::WHY),
                fn () => $catalogue->approvePlan($plan, $person, self::WHY),
                fn () => $catalogue->createBundle(['name' => 'Sneaky pair', 'amount_minor' => 100, 'currency' => 'ZAR'], ['hr', 'forms'], $person, self::WHY),
            ] as $attempt) {
                try {
                    $attempt();
                } catch (AuthorizationException) {
                    $refused++;
                }
            }
        }

        $this->assertSame(10, $refused);
        $this->assertSame('operational', $entry->fresh()->operational_status);
        $this->assertNull($plan->fresh()->approved_at);
        $this->assertSame(1, PlatformPlan::count());
        $this->assertSame(0, PlatformBundle::count());
    }

    public function test_a_new_plan_is_a_draft_and_is_sold_only_after_its_price_is_approved_and_it_is_switched_on(): void
    {
        $admin = $this->administrator();
        $team = $this->company();

        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/plans', [
            'name' => 'Team', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 24900, 'currency' => 'zar', 'trial_days' => 14,
            'approved_at' => now()->toDateTimeString(), 'active' => 1, 'reason' => self::WHY,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan = PlatformPlan::sole();
        $this->assertSame(['hr', 'team', 24900, 'ZAR', 'month'], [$plan->platform_key, $plan->slug, $plan->amount_minor, $plan->currency, $plan->interval]);
        $this->assertNull($plan->approved_at, 'approval cannot be sent with the form');
        $this->assertFalse($plan->isSellable());
        $this->assertSame('unpriced_free', $this->access()->offer('hr'), 'an unapproved price changes nothing for a company');

        // Approving needs the tick and the reason.
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id.'/approve', ['reason' => self::WHY])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id.'/approve', ['confirm' => 1])->assertSessionHasErrors('reason');
        $this->assertNull($plan->fresh()->approved_at);

        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id.'/approve', ['confirm' => 1, 'reason' => 'Owner approved the price on 14 November.'])->assertSessionHasNoErrors();
        $this->assertTrue($plan->fresh()->isSellable());
        $this->assertSame('paid', $this->access()->offer('hr'));
        $this->assertSame('trial_available', $this->access()->businessState($team, 'hr'));

        $approval = PlatformCatalogueEvent::where('type', 'price_approved')->sole();
        $this->assertSame([$admin->id, 'plan', 'hr/team', 'Owner approved the price on 14 November.'], [$approval->actor_id, $approval->subject_type, $approval->subject_key, $approval->reason]);

        // Switched off: approved, but not on sale.
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id, ['name' => 'Team', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 24900, 'currency' => 'ZAR', 'trial_days' => 14, 'active' => 0, 'reason' => self::WHY]);
        $this->assertNotNull($plan->fresh()->approved_at);
        $this->assertFalse($plan->fresh()->isSellable());
        $this->assertSame('unpriced_free', $this->access()->offer('hr'));
    }

    public function test_changing_a_price_withdraws_the_approval_and_renaming_does_not(): void
    {
        $admin = $this->administrator();
        $plan = $this->plan('hr', [], true, $admin);
        $same = ['name' => 'Team monthly', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 24900, 'currency' => 'ZAR', 'active' => 1, 'reason' => self::WHY];

        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id, $same)->assertSessionHasNoErrors();
        $this->assertTrue($plan->fresh()->isSellable(), 'a new name is not a new price');

        foreach ([['amount_minor' => 29900], ['currency' => 'USD'], ['interval' => 'year'], ['model' => 'per_user']] as $change) {
            app(CatalogueAdmin::class)->approvePlan($plan->fresh(), $admin, self::WHY);
            $this->assertTrue($plan->fresh()->isSellable());

            $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id, $change + $same)->assertSessionHasNoErrors();

            $this->assertNull($plan->fresh()->approved_at, 'changing '.array_key_first($change).' kept the approval');
            $this->assertFalse($plan->fresh()->isSellable());
            $this->assertSame('unpriced_free', $this->access()->offer('hr'));

            $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id, $same);
        }

        $this->assertNull($plan->fresh()->approved_at, 'putting the old price back does not bring the approval back');
    }

    public function test_a_price_change_never_alters_an_entitlement_a_company_already_holds(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $plan = $this->plan('hr', ['trial_days' => 14], true, $admin);
        $this->entitlements()->startTrial($team, 'hr', $admin, $plan, null, self::WHY);

        $before = DB::table('platform_entitlements')->where('team_id', $team->id)->first();
        $events = DB::table('platform_entitlement_events')->count();
        $outbox = DB::table('entitlement_outbox')->count();

        $this->travel(5)->minutes();
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id, ['name' => 'Team', 'model' => 'subscription', 'interval' => 'year', 'amount_minor' => 249000, 'currency' => 'ZAR', 'trial_days' => 30, 'active' => 0, 'reason' => self::WHY]);
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id.'/approve', ['confirm' => 1, 'reason' => self::WHY]);
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$plan->id.'/withdraw', ['reason' => self::WHY])->assertSessionHasNoErrors();

        $this->assertSame(249000, $plan->fresh()->amount_minor);
        $this->assertEquals($before, DB::table('platform_entitlements')->where('team_id', $team->id)->first());
        $this->assertSame($events, DB::table('platform_entitlement_events')->count());
        $this->assertSame($outbox, DB::table('entitlement_outbox')->count());
        $this->assertTrue($this->access()->entitlement($team, 'hr')->grants());
    }

    public function test_price_rules_hold_whatever_is_typed(): void
    {
        $admin = $this->administrator();
        $base = ['name' => 'Plan', 'model' => 'subscription', 'reason' => self::WHY];

        // An amount without a currency, a free plan that costs something, a priced plan that costs nothing.
        foreach ([['amount_minor' => 100], ['currency' => 'ZAR'], ['model' => 'free', 'amount_minor' => 100, 'currency' => 'ZAR'], ['amount_minor' => 0, 'currency' => 'ZAR']] as $bad) {
            $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/plans', $bad + $base)->assertSessionHasErrors();
        }

        foreach ([['amount_minor' => -1, 'currency' => 'ZAR'], ['amount_minor' => 10.5, 'currency' => 'ZAR'], ['amount_minor' => 100, 'currency' => 'RAND'], ['model' => 'gift']] as $bad) {
            $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/plans', $bad + $base)->assertSessionHasErrors();
        }

        // Core platforms and platforms with their own sign-in have no plans.
        $this->actingAs($admin)->post('/admin/platforms/catalogue/files/plans', $base)->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post('/admin/platforms/catalogue/charts/plans', $base)->assertSessionHasErrors('refused');
        $this->assertSame(0, PlatformPlan::count());

        // A plan with no price can exist as a draft and cannot be approved.
        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/plans', $base)->assertSessionHasNoErrors();
        $draft = PlatformPlan::sole();
        $this->assertNull($draft->amount_minor);
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$draft->id.'/approve', ['confirm' => 1, 'reason' => self::WHY])->assertSessionHasErrors('refused');
        $this->assertNull($draft->fresh()->approved_at);

        // A free plan is approved like any other, and then the platform is free to switch on.
        $this->actingAs($admin)->post('/admin/platforms/catalogue/forms/plans', ['name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'currency' => 'ZAR', 'active' => 1, 'reason' => self::WHY])->assertSessionHasNoErrors();
        $free = PlatformPlan::where('platform_key', 'forms')->sole();
        $this->assertSame('unpriced_free', $this->access()->offer('forms'));
        $this->actingAs($admin)->post('/admin/platforms/plans/'.$free->id.'/approve', ['confirm' => 1, 'reason' => self::WHY]);
        $this->assertSame('free', $this->access()->offer('forms'));

        // Two plans with one name get different slugs.
        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/plans', $base);
        $this->assertSame(['plan', 'plan-2'], PlatformPlan::where('platform_key', 'hr')->orderBy('id')->pluck('slug')->all());
    }

    public function test_a_bundle_follows_the_same_approval_rule(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->post('/admin/platforms/bundles', [
            'name' => 'Office pair', 'description' => 'People and projects together.', 'platforms' => ['hr', 'projects'], 'interval' => 'month',
            'amount_minor' => 39900, 'currency' => 'ZAR', 'active' => 1, 'approved_at' => now()->toDateTimeString(), 'reason' => self::WHY,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bundle = PlatformBundle::with('items')->sole();
        $this->assertSame(['office-pair', 39900, null], [$bundle->slug, $bundle->amount_minor, $bundle->approved_at]);
        $this->assertSame(['hr', 'projects'], $bundle->items->pluck('platform_key')->sort()->values()->all());

        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id.'/approve', ['reason' => self::WHY])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id.'/approve', ['confirm' => 1, 'reason' => self::WHY])->assertSessionHasNoErrors();
        $this->assertNotNull($bundle->fresh()->approved_at);

        $same = ['name' => 'Office pair', 'platforms' => ['hr', 'projects'], 'interval' => 'month', 'amount_minor' => 39900, 'currency' => 'ZAR', 'active' => 1, 'reason' => self::WHY];

        // A new description keeps the approval; a new price or new contents withdraws it.
        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id, ['description' => 'Reworded.'] + $same)->assertSessionHasNoErrors();
        $this->assertNotNull($bundle->fresh()->approved_at);

        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id, ['amount_minor' => 44900] + $same);
        $this->assertNull($bundle->fresh()->approved_at);

        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id, $same);
        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id.'/approve', ['confirm' => 1, 'reason' => self::WHY]);
        $this->actingAs($admin)->post('/admin/platforms/bundles/'.$bundle->id, ['platforms' => ['hr', 'projects', 'forms']] + $same)->assertSessionHasNoErrors();
        $this->assertNull($bundle->fresh()->approved_at);
        $this->assertSame(3, $bundle->items()->count());

        $types = PlatformCatalogueEvent::where('subject_type', 'bundle')->orderBy('id')->pluck('type')->all();
        $this->assertSame(['bundle_created', 'price_approved', 'bundle_updated', 'bundle_updated', 'bundle_updated', 'price_approved', 'bundle_updated'], $types);
        $this->assertSame(0, PlatformCatalogueEvent::whereNull('reason')->count());
        $this->assertSame(0, PlatformCatalogueEvent::whereNull('actor_id')->count());
    }

    public function test_a_bundle_holds_at_least_two_platforms_that_are_switched_on_here(): void
    {
        $admin = $this->administrator();
        $base = ['name' => 'Pair', 'amount_minor' => 100, 'currency' => 'ZAR', 'reason' => self::WHY];

        $this->actingAs($admin)->post('/admin/platforms/bundles', ['platforms' => ['hr']] + $base)->assertSessionHasErrors('platforms');
        $this->actingAs($admin)->post('/admin/platforms/bundles', ['platforms' => ['hr', 'files']] + $base)->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post('/admin/platforms/bundles', ['platforms' => ['hr', 'charts']] + $base)->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post('/admin/platforms/bundles', ['platforms' => ['hr', 'nonsense']] + $base)->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post('/admin/platforms/bundles', ['platforms' => ['hr', 'forms'], 'amount_minor' => 0] + $base)->assertSessionHasErrors();

        $this->assertSame(0, PlatformBundle::count());
    }

    public function test_the_audit_trail_cannot_be_edited_or_deleted(): void
    {
        $this->plan();
        $event = PlatformCatalogueEvent::firstOrFail();

        foreach ([fn () => $event->update(['reason' => 'Rewritten.']), fn () => $event->delete()] as $tamper) {
            try {
                $tamper();
                $this->fail('The audit trail was changed.');
            } catch (LogicException) {
                $this->assertSame(self::WHY, $event->fresh()->reason);
            }
        }
    }

    public function test_the_catalogue_page_flags_what_has_no_approved_price(): void
    {
        $admin = $this->administrator();
        $this->plan('hr', [], false, $admin);
        $this->plan('forms', [], true, $admin);

        $page = $this->actingAs($admin)->get('/admin/platforms')->assertOk();
        $page->assertSee('Pricing not configured')->assertSee('Pricing not approved')->assertSee('1 plan on sale')->assertSee('Core: included for every company');

        $this->assertSame(0, PlatformEntitlement::count());
    }
}
