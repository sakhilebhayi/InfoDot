<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPlan;
use App\Models\PlatformUserAccess;
use App\Services\Platforms\AccessDecision;
use App\Services\Platforms\EntitlementRefused;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The R-50 scenarios, against the real backend: what a new company has, how it gets more, who inside it gets in, what
 * each refusal is called, and that one company's entitlements mean nothing to another.
 */
class PlatformEntitlementScenariosTest extends PlatformTestCase
{
    public function test_a_new_company_has_only_the_free_core(): void
    {
        $team = $this->company();
        $owner = $team->owner;

        foreach (['files', 'docs', 'tasks', 'finance'] as $core) {
            $this->assertTrue($this->access()->decide($owner, $core)->allowed, $core);
        }

        foreach (['forms', 'hr', 'projects', 'analytics', 'agents', 'central'] as $other) {
            $decision = $this->access()->decide($owner, $other);
            $this->assertFalse($decision->allowed, $other);
            $this->assertSame(AccessDecision::NOT_ACTIVATED, $decision->reason, $other);
            $this->assertTrue($decision->team->is($team));
        }

        $this->assertSame(0, PlatformEntitlement::count(), 'the core needs no entitlement rows');
    }

    public function test_an_owner_activates_an_unpriced_platform_and_it_is_free_while_pricing_is_finalised(): void
    {
        $team = $this->company();

        $entitlement = $this->entitlements()->activate($team, 'forms', $team->owner);

        $this->assertSame('active', $entitlement->status);
        $this->assertSame('unpriced_free', $entitlement->source);
        $this->assertSame('everyone', $entitlement->access_mode);
        $this->assertNotNull($entitlement->started_at);
        $this->assertTrue($this->access()->decide($team->owner, 'forms')->allowed);
        $this->assertSame('activated', PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', 'forms')->value('type'));
    }

    public function test_a_platform_with_an_approved_free_plan_is_activated_as_free(): void
    {
        $team = $this->company();
        PlatformPlan::create(['platform_key' => 'forms', 'slug' => 'free', 'name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);

        $this->assertSame('free', $this->entitlements()->activate($team, 'forms', $team->owner)->source);
    }

    public function test_a_plan_that_is_not_approved_or_has_no_price_does_not_make_a_platform_paid(): void
    {
        $team = $this->company();
        PlatformPlan::create(['platform_key' => 'forms', 'slug' => 'team', 'name' => 'Draft', 'model' => 'subscription', 'amount_minor' => 9900, 'currency' => 'ZAR', 'approved_at' => null, 'active' => true]);
        PlatformPlan::create(['platform_key' => 'forms', 'slug' => 'blank', 'name' => 'No price', 'model' => 'subscription', 'amount_minor' => null, 'currency' => null, 'approved_at' => now(), 'active' => true]);

        $this->assertSame('unpriced_free', $this->entitlements()->activate($team, 'forms', $team->owner)->source);
    }

    public function test_nothing_in_this_step_can_create_a_paid_entitlement(): void
    {
        $team = $this->company();
        PlatformPlan::create(['platform_key' => 'forms', 'slug' => 'team', 'name' => 'Team', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 9900, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);

        try {
            $this->entitlements()->activate($team, 'forms', $team->owner);
            $this->fail('a platform with an approved price was activated without a payment');
        } catch (EntitlementRefused $e) {
            $this->assertStringContainsString('Nothing was changed and nothing was charged', $e->getMessage());
        }

        $this->assertSame(0, PlatformEntitlement::count());
        $this->assertSame(0, DB::table('entitlement_outbox')->count());
        $this->assertSame(AccessDecision::NOT_ACTIVATED, $this->access()->decide($team->owner, 'forms')->reason);
    }

    public function test_several_platforms_are_activated_independently(): void
    {
        $team = $this->company();

        $this->entitlements()->activate($team, 'forms', $team->owner);
        $this->entitlements()->activate($team, 'hr', $team->owner);

        $this->assertTrue($this->access()->decide($team->owner, 'forms')->allowed);
        $this->assertTrue($this->access()->decide($team->owner, 'hr')->allowed);
        $this->assertSame(AccessDecision::NOT_ACTIVATED, $this->access()->decide($team->owner, 'projects')->reason);

        $this->entitlements()->deactivate($team, 'forms', $team->owner);

        $this->assertSame(AccessDecision::CANCELLED, $this->access()->decide($team->owner, 'forms')->reason);
        $this->assertTrue($this->access()->decide($team->owner, 'hr')->allowed, 'switching one off leaves the other alone');
    }

    public function test_activating_twice_makes_one_entitlement_and_one_event(): void
    {
        $team = $this->company();

        $first = $this->entitlements()->activate($team, 'forms', $team->owner);
        $second = $this->entitlements()->activate($team, 'forms', $team->owner);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, PlatformEntitlement::count());
        $this->assertSame(1, PlatformEntitlementEvent::count());
        $this->assertSame(1, DB::table('entitlement_outbox')->count());
    }

    public function test_an_employee_who_is_not_selected_is_refused_while_a_selected_one_gets_in(): void
    {
        $team = $this->company();
        $chosen = $this->memberOf($team);
        $other = $this->memberOf($team);
        $admin = $this->memberOf($team, 'admin');

        $this->entitlements()->activate($team, 'hr', $team->owner);
        $this->assertTrue($this->access()->decide($other, 'hr')->allowed, 'everyone in the company gets in by default');

        $this->entitlements()->setAccessMode($team, 'hr', 'selected', $team->owner);
        $this->entitlements()->grantUser($team, 'hr', $chosen, $team->owner);

        $this->assertTrue($this->access()->decide($chosen, 'hr')->allowed);
        $this->assertSame(AccessDecision::NOT_PERMITTED_FOR_YOU, $this->access()->decide($other, 'hr')->reason);
        $this->assertTrue($this->access()->decide($team->owner, 'hr')->allowed, 'the owner always');
        $this->assertTrue($this->access()->decide($admin, 'hr')->allowed, 'administrators always');

        $this->entitlements()->revokeUser($team, 'hr', $chosen, $team->owner);
        $this->assertSame(AccessDecision::NOT_PERMITTED_FOR_YOU, $this->access()->decide($chosen, 'hr')->reason);

        $this->entitlements()->setAccessMode($team, 'hr', 'everyone', $team->owner);
        $this->assertTrue($this->access()->decide($other, 'hr')->allowed);
    }

    public function test_a_person_outside_the_company_cannot_be_selected(): void
    {
        $team = $this->company();
        $stranger = $this->person();
        $this->entitlements()->activate($team, 'hr', $team->owner);

        $this->expectException(EntitlementRefused::class);

        try {
            $this->entitlements()->grantUser($team, 'hr', $stranger, $team->owner);
        } finally {
            $this->assertSame(0, PlatformUserAccess::count());
        }
    }

    public function test_activation_needs_no_business_profile_no_description_and_no_other_milestone(): void
    {
        $team = $this->company();

        $this->assertNull($team->profile, 'no business profile');
        $this->assertNull($team->industry);
        $this->assertSame(0, $team->users()->count(), 'no other people');
        $this->assertSame(0, DB::table('questions')->count() + DB::table('solutions')->count(), 'no activity');

        $this->entitlements()->activate($team, 'projects', $team->owner);

        $this->assertTrue($this->access()->decide($team->owner, 'projects')->allowed);
    }

    public function test_a_person_switches_between_two_activated_platforms(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms', 'projects');

        $this->actingAs($team->owner)->get('/go/forms')->assertOk()->assertSee('https://forms.infodot.co.za/auth/ecosystem', false);
        $this->actingAs($team->owner)->get('/go/projects')->assertOk()->assertSee('https://projects.infodot.co.za/auth/ecosystem', false);
        $this->actingAs($team->owner)->get('/go/forms')->assertOk();

        $this->assertSame(['ecosystem-handoff:forms', 'ecosystem-handoff:projects', 'ecosystem-handoff:forms'], DB::table('personal_access_tokens')->orderBy('id')->pluck('name')->all());
    }

    public function test_an_operational_but_unactivated_platform_is_refused_with_the_way_to_activate_it(): void
    {
        $team = $this->company();

        $this->assertSame('operational', PlatformCatalogue::where('key', 'forms')->value('operational_status'));

        $this->actingAs($team->owner)->get('/go/forms')
            ->assertStatus(403)
            ->assertSee('has not switched on Dot.Forms')
            ->assertSee('action="'.route('platform.activate', ['platform' => 'forms']).'"', false)
            ->assertSee('Switch on Dot.Forms');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_platform_in_maintenance_is_temporarily_unavailable_and_returns_when_set_back(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');

        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'maintenance']);

        $this->assertSame(AccessDecision::TEMPORARILY_UNAVAILABLE, $this->access()->decide($team->owner, 'forms')->reason);
        $this->actingAs($team->owner)->get('/go/forms')->assertStatus(403)->assertSee('Dot.Forms is temporarily unavailable')->assertDontSee('Switch on');
        $this->assertSame('active', PlatformEntitlement::where('team_id', $team->id)->value('status'), 'the entitlement is untouched');

        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'operational']);

        $this->assertTrue($this->access()->decide($team->owner, 'forms')->allowed);
        $this->actingAs($team->owner)->get('/go/forms')->assertOk();
    }

    public function test_a_degraded_platform_still_opens_and_one_not_launched_does_not(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');

        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'degraded']);
        $this->assertTrue($this->access()->decide($team->owner, 'forms')->allowed);

        foreach (['unavailable' => AccessDecision::TEMPORARILY_UNAVAILABLE, 'development' => AccessDecision::NOT_LAUNCHED, 'not_launched' => AccessDecision::NOT_LAUNCHED] as $status => $reason) {
            PlatformCatalogue::where('key', 'forms')->update(['operational_status' => $status]);
            $this->assertSame($reason, $this->access()->decide($team->owner, 'forms')->reason, $status);
        }

        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'operational', 'discoverable' => false]);
        $this->assertSame(AccessDecision::NOT_LAUNCHED, $this->access()->decide($team->owner, 'forms')->reason, 'hidden by an administrator');
    }

    public function test_deactivating_keeps_the_entitlement_its_history_and_everything_else(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $this->activatePlatforms($team, 'forms', 'hr');
        $this->entitlements()->setAccessMode($team, 'forms', 'selected', $team->owner);
        $this->entitlements()->grantUser($team, 'forms', $member, $team->owner);

        $before = ['teams' => DB::table('teams')->count(), 'users' => DB::table('users')->count(), 'team_user' => DB::table('team_user')->count(), 'access' => PlatformUserAccess::count(), 'events' => PlatformEntitlementEvent::count()];

        $entitlement = $this->entitlements()->deactivate($team, 'forms', $team->owner);

        $this->assertSame('cancelled', $entitlement->status);
        $this->assertNotNull($entitlement->cancelled_at);
        $this->assertSame(2, PlatformEntitlement::where('team_id', $team->id)->count(), 'the row stays');
        $this->assertSame($before['events'] + 1, PlatformEntitlementEvent::count(), 'history only grows');
        $this->assertSame(['activated', 'access_mode_changed', 'user_granted', 'deactivated'], PlatformEntitlementEvent::where('platform_key', 'forms')->orderBy('id')->pluck('type')->all());
        $this->assertSame($before['access'], PlatformUserAccess::count(), 'the chosen people are remembered');
        $this->assertSame([$before['teams'], $before['users'], $before['team_user']], [DB::table('teams')->count(), DB::table('users')->count(), DB::table('team_user')->count()]);
        $this->assertSame(AccessDecision::CANCELLED, $this->access()->decide($team->owner, 'forms')->reason);

        // It can be switched on again at once, and comes back as it was.
        $again = $this->entitlements()->activate($team, 'forms', $team->owner);
        $this->assertSame('active', $again->status);
        $this->assertNull($again->cancelled_at);
        $this->assertSame('selected', $again->access_mode);
        $this->assertTrue($this->access()->decide($member, 'forms')->allowed);
    }

    public function test_only_the_owner_deactivates_and_an_administrator_of_the_company_activates(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');

        $this->assertSame('active', $this->entitlements()->activate($team, 'forms', $admin)->status);

        try {
            $this->entitlements()->deactivate($team, 'forms', $admin);
            $this->fail('an administrator switched a platform off');
        } catch (AuthorizationException) {
            $this->assertSame('active', PlatformEntitlement::value('status'));
        }
    }

    public function test_another_companys_entitlements_are_invisible_and_unusable(): void
    {
        $ours = $this->company('Northwind Freight');
        $theirs = $this->company('Harbour Logistics');
        $this->activatePlatforms($theirs, 'forms');

        // Their activation does nothing for us.
        $this->assertSame(AccessDecision::NOT_ACTIVATED, $this->access()->decide($ours->owner, 'forms')->reason);
        $this->actingAs($ours->owner)->get('/go/forms')->assertStatus(403)->assertDontSee('Harbour Logistics');

        // We cannot be judged against their company, change it, or read it.
        $this->assertSame(AccessDecision::NOT_A_MEMBER, $this->access()->decideFor($ours->owner, $theirs, 'forms')->reason);

        foreach ([
            fn () => $this->entitlements()->activate($theirs, 'hr', $ours->owner),
            fn () => $this->entitlements()->deactivate($theirs, 'forms', $ours->owner),
            fn () => $this->entitlements()->setAccessMode($theirs, 'forms', 'selected', $ours->owner),
            fn () => $this->entitlements()->grantUser($theirs, 'forms', $theirs->owner, $ours->owner),
            fn () => $this->entitlements()->revokeUser($theirs, 'forms', $theirs->owner, $ours->owner),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('a person changed another company\'s platforms');
            } catch (AuthorizationException) {
                // refused
            }
        }

        $this->assertSame(['forms'], PlatformEntitlement::where('team_id', $theirs->id)->pluck('platform_key')->all());
        $this->assertSame('active', PlatformEntitlement::where('team_id', $theirs->id)->value('status'));
        $this->assertSame(0, PlatformEntitlement::where('team_id', $ours->id)->count());
    }

    public function test_a_member_cannot_activate(): void
    {
        $team = $this->company();

        foreach (['member', 'manager', 'editor'] as $role) {
            $member = $this->memberOf($team, $role);

            try {
                $this->entitlements()->activate($team, 'forms', $member);
                $this->fail("a $role activated a platform");
            } catch (AuthorizationException) {
                // refused
            }

            $this->actingAs($member)->post('/platforms/forms/activate', ['company' => $team->uuid])->assertForbidden();
            $this->actingAs($member)->get('/go/forms')->assertStatus(403)->assertDontSee('Switch on Dot.Forms')->assertSee('Ask '.$team->owner->name);
        }

        $this->assertSame(0, PlatformEntitlement::count());
    }

    public function test_a_forged_activation_naming_another_company_is_refused(): void
    {
        $ours = $this->company('Northwind Freight');
        $theirs = $this->company('Harbour Logistics');

        // Our owner posts their company's id: nothing is switched on, for them or for us.
        $this->actingAs($ours->owner)->post('/platforms/forms/activate', ['company' => $theirs->uuid])->assertStatus(409);
        $this->actingAs($ours->owner)->post('/platforms/forms/activate', ['company' => $theirs->uuid, 'team_id' => $theirs->id])->assertStatus(409);
        $this->actingAs($ours->owner)->post('/platforms/forms/activate')->assertStatus(409);
        $this->actingAs($ours->owner)->post('/platforms/forms/activate', ['company' => ['a', 'b']])->assertStatus(409);

        $this->assertSame(0, PlatformEntitlement::count());
    }

    public function test_an_activation_without_the_csrf_token_is_refused_and_with_it_goes_through(): void
    {
        $team = $this->company();

        // CSRF verification is skipped while tests run: run the request in production mode, where the middleware is active.
        $this->app['env'] = 'production';

        $this->actingAs($team->owner)->post('/platforms/forms/activate', ['company' => $team->uuid], ['Origin' => 'https://evil.example'])->assertStatus(419);
        $this->actingAs($team->owner)->withSession(['_token' => 'the-real-one'])->post('/platforms/forms/activate', ['company' => $team->uuid, '_token' => 'a-guess'])->assertStatus(419);
        $this->assertSame(0, PlatformEntitlement::count());

        $this->actingAs($team->owner)->withSession(['_token' => 'the-real-one'])->post('/platforms/forms/activate', ['company' => $team->uuid, '_token' => 'the-real-one'])->assertRedirect();
        $this->assertSame(1, PlatformEntitlement::count());
    }

    public function test_an_activation_from_someone_signed_out_or_by_a_link_is_refused(): void
    {
        $team = $this->company();

        $this->post('/platforms/forms/activate', ['company' => $team->uuid])->assertRedirect('/login');
        $this->actingAs($team->owner)->get('/platforms/forms/activate')->assertStatus(405);

        $this->assertSame(0, PlatformEntitlement::count());
    }

    public function test_a_person_who_is_not_in_the_company_cannot_activate_for_it(): void
    {
        $team = $this->company();
        $outsider = $this->person();

        // Their current company is their own workspace, so the company named in the form does not match.
        $this->actingAs($outsider)->post('/platforms/forms/activate', ['company' => $team->uuid])->assertStatus(409);

        // Even pointed at the company by a tampered current_team_id, the policy refuses a non-member.
        $outsider->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($outsider->fresh())->post('/platforms/forms/activate', ['company' => $team->uuid])->assertForbidden();
        $this->assertSame(AccessDecision::NOT_A_MEMBER, $this->access()->decide($outsider->fresh(), 'forms')->reason);

        $this->assertSame(0, PlatformEntitlement::count());
    }

    public function test_a_replayed_activation_changes_nothing_the_second_time(): void
    {
        $team = $this->company();

        $this->actingAs($team->owner)->post('/platforms/forms/activate', ['company' => $team->uuid])->assertRedirect(route('platform.launch', ['platform' => 'forms']));
        $this->actingAs($team->owner)->post('/platforms/forms/activate', ['company' => $team->uuid])->assertRedirect(route('platform.launch', ['platform' => 'forms']));

        $this->assertSame(1, PlatformEntitlement::count());
        $this->assertSame(1, PlatformEntitlementEvent::count());
    }

    public function test_a_suspended_entitlement_refuses_with_its_own_reason_and_restoring_brings_it_back(): void
    {
        $team = $this->company();
        $administrator = $this->administrator();
        $this->activatePlatforms($team, 'forms');

        $this->entitlements()->suspend($team, 'forms', $administrator, 'under review');

        $this->assertSame(AccessDecision::SUSPENDED, $this->access()->decide($team->owner, 'forms')->reason);
        $this->actingAs($team->owner)->get('/go/forms')->assertStatus(403)->assertSee('Dot.Forms is suspended for '.$team->name)->assertDontSee('Switch on');

        // The company cannot lift it by activating again.
        try {
            $this->entitlements()->activate($team, 'forms', $team->owner);
            $this->fail('the owner lifted a suspension');
        } catch (EntitlementRefused) {
            $this->assertSame('suspended', PlatformEntitlement::value('status'));
        }
        $this->actingAs($team->owner)->post('/platforms/forms/activate', ['company' => $team->uuid]);
        $this->assertSame('suspended', PlatformEntitlement::value('status'));

        $this->entitlements()->restore($team, 'forms', $administrator);

        $this->assertSame('active', PlatformEntitlement::value('status'));
        $this->assertTrue($this->access()->decide($team->owner, 'forms')->allowed);
        $this->assertSame(['activated', 'suspended', 'restored'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());
        $this->assertSame($administrator->id, PlatformEntitlementEvent::where('type', 'suspended')->value('actor_id'));
    }

    public function test_only_a_platform_administrator_suspends_restores_or_grants_complimentary_access(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');
        $moderator = $this->person();
        $moderator->forceFill(['is_moderator' => true])->save();

        foreach ([$team->owner, $moderator] as $person) {
            foreach ([
                fn () => $this->entitlements()->suspend($team, 'forms', $person),
                fn () => $this->entitlements()->restore($team, 'forms', $person),
                fn () => $this->entitlements()->grantComplimentary($team, 'hr', $person),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('someone who is not a platform administrator used an administrator power');
                } catch (AuthorizationException) {
                    // refused
                }
            }
        }

        $this->assertSame(['forms'], PlatformEntitlement::pluck('platform_key')->all());
        $this->assertSame('active', PlatformEntitlement::value('status'));
    }

    public function test_complimentary_access_is_active_until_its_end_date(): void
    {
        $team = $this->company();
        $administrator = $this->administrator();

        $entitlement = $this->entitlements()->grantComplimentary($team, 'analytics', $administrator, now()->addDays(30), 'launch partner');

        $this->assertSame(['active', 'complimentary', $administrator->id], [$entitlement->status, $entitlement->source, $entitlement->granted_by]);
        $this->assertTrue($this->access()->decide($team->owner, 'analytics')->allowed);

        $this->travel(31)->days();

        $this->assertSame(AccessDecision::EXPIRED, $this->access()->decide($team->owner, 'analytics')->reason);
    }

    public function test_a_personal_workspace_gets_the_core_only(): void
    {
        $person = $this->person();

        foreach (['files', 'docs', 'tasks', 'finance'] as $core) {
            $this->assertTrue($this->access()->decide($person, $core)->allowed, $core);
        }

        $this->assertSame(AccessDecision::NO_PERSONAL_ACCESS, $this->access()->decide($person, 'forms')->reason);
        $this->actingAs($person)->get('/go/forms')->assertStatus(403)->assertSee('Dot.Forms is for companies')->assertSee('Create a company')->assertDontSee('Switch on');

        try {
            $this->entitlements()->activate($person->currentTeam, 'forms', $person);
            $this->fail('a personal workspace activated a platform');
        } catch (AuthorizationException) {
            $this->assertSame(0, PlatformEntitlement::count());
        }

        $this->actingAs($person)->post('/platforms/forms/activate', ['company' => $person->currentTeam->uuid])->assertForbidden();
    }

    public function test_the_core_cannot_be_activated_or_switched_off_and_never_gets_a_row(): void
    {
        $team = $this->company();

        foreach ([fn () => $this->entitlements()->activate($team, 'finance', $team->owner), fn () => $this->entitlements()->deactivate($team, 'finance', $team->owner)] as $attempt) {
            try {
                $attempt();
                $this->fail('the core was changed');
            } catch (EntitlementRefused) {
                // refused
            }
        }

        $this->assertSame(0, PlatformEntitlement::count());
        $this->assertTrue($this->access()->decide($team->owner, 'finance')->allowed);
    }

    public function test_the_paid_states_are_honoured_even_though_nothing_here_creates_them(): void
    {
        $team = $this->company();
        $row = fn (array $values) => PlatformEntitlement::updateOrCreate(['team_id' => $team->id, 'platform_key' => 'forms'], $values + ['source' => 'paid', 'access_mode' => 'everyone', 'trial_ends_at' => null, 'grace_until' => null, 'current_period_ends_at' => null]);
        $reason = fn () => $this->access()->decide($team->owner, 'forms')->reason;

        $row(['status' => 'pending']);
        $this->assertSame(AccessDecision::PAYMENT_REQUIRED, $reason());

        $row(['status' => 'trial', 'trial_ends_at' => now()->addDay()]);
        $this->assertNull($reason());
        $row(['status' => 'trial', 'trial_ends_at' => now()->subMinute()]);
        $this->assertSame(AccessDecision::TRIAL_ENDED, $reason());

        $row(['status' => 'payment_required', 'grace_until' => now()->addDay()]);
        $this->assertNull($reason(), 'within grace');
        $row(['status' => 'payment_required', 'grace_until' => now()->subMinute()]);
        $this->assertSame(AccessDecision::PAYMENT_REQUIRED, $reason());

        $row(['status' => 'expired']);
        $this->assertSame(AccessDecision::EXPIRED, $reason());

        $row(['status' => 'cancelled', 'current_period_ends_at' => now()->addDay()]);
        $this->assertNull($reason(), 'cancelled, with paid time left');
        $row(['status' => 'cancelled', 'current_period_ends_at' => now()->subMinute()]);
        $this->assertSame(AccessDecision::CANCELLED, $reason());

        // A company cannot talk its way out of a payment state by pressing Activate.
        $row(['status' => 'payment_required']);
        $this->expectException(EntitlementRefused::class);
        $this->entitlements()->activate($team, 'forms', $team->owner);
    }

    public function test_a_person_who_is_not_verified_or_is_suspended_is_never_allowed(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms');

        $this->assertSame(AccessDecision::NOT_SIGNED_IN, $this->access()->decide(null, 'forms')->reason);

        $team->owner->forceFill(['email_verified_at' => null])->save();
        $this->assertSame(AccessDecision::NOT_SIGNED_IN, $this->access()->decide($team->owner->fresh(), 'files')->reason);

        $team->owner->forceFill(['email_verified_at' => now(), 'suspended_at' => now()])->save();
        $this->assertSame(AccessDecision::NOT_SIGNED_IN, $this->access()->decide($team->owner->fresh(), 'forms')->reason);
    }

    public function test_infodot_itself_bridge_services_and_unknown_keys_are_outside_this_system(): void
    {
        $person = $this->person();

        foreach (['infodot', 'memory', 'dopemine', 'no-such-platform'] as $key) {
            $decision = $this->access()->decide($person, $key);
            $this->assertTrue($decision->allowed, $key);
            $this->assertSame(AccessDecision::NOT_GOVERNED, $decision->reason, $key);
            $this->assertFalse($decision->governed());
        }
    }

    public function test_a_platform_with_its_own_sign_in_is_managed_in_the_platform_and_cannot_be_activated_here(): void
    {
        $team = $this->company();

        foreach (['charts', 'mines'] as $key) {
            $decision = $this->access()->decide($team->owner, $key);
            $this->assertTrue($decision->allowed);
            $this->assertTrue($decision->managedInPlatform(), $key);

            try {
                $this->entitlements()->activate($team, $key, $team->owner);
                $this->fail("$key was activated");
            } catch (EntitlementRefused) {
                // refused
            }
        }

        $this->assertSame(0, PlatformEntitlement::count());
    }
}
