<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPlan;
use App\Models\PlatformUserAccess;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Facades\DB;

/**
 * What a company can do from the catalogue (R-50 section 6): switch a free or unpriced platform on, switch it off,
 * choose who opens it. Each action behind a page that says what will happen, each for the current company only, and
 * each only for the people TeamPolicy names. The owner and administrators are told in InfoDot afterwards.
 */
class PlatformCatalogueActionsTest extends PlatformTestCase
{
    private function entitlement(Team $team, string $key): ?PlatformEntitlement
    {
        return PlatformEntitlement::where('team_id', $team->id)->where('platform_key', $key)->first();
    }

    private function events(Team $team, string $key): array
    {
        return PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', $key)->orderBy('id')->pluck('type')->all();
    }

    public function test_the_confirmation_says_plainly_what_switching_an_unpriced_platform_on_means(): void
    {
        $team = $this->company('Northwind Freight');
        $this->memberOf($team);

        $html = $this->actingAs($team->owner)->get('/platforms/forms/switch-on')->assertOk()->getContent();

        $this->assertStringContainsString('Switch on Dot.Forms for Northwind Freight?', $html);
        $this->assertStringContainsString('everyone in Northwind Freight (2 people today)', $html);
        $this->assertStringContainsString('It is free for now.', $html);
        $this->assertStringContainsString('No price has been set for Dot.Forms, so nothing is charged', $html);
        $this->assertStringContainsString('No charge will ever be made without new, explicit consent.', $html);
        $this->assertStringContainsString('You can switch it off again whenever you like', $html);
        $this->assertStringContainsString('data-platform-confirm="on"', $html);
        $this->assertNull($this->entitlement($team, 'forms'), 'opening the page changes nothing');
    }

    public function test_the_confirmation_of_an_approved_free_platform_says_it_is_free(): void
    {
        $team = $this->company();
        PlatformPlan::create(['platform_key' => 'forms', 'slug' => 'free', 'name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);

        $this->actingAs($team->owner)->get('/platforms/forms/switch-on')->assertOk()
            ->assertSee('It is free.')
            ->assertSee('no charge will ever be made without new, explicit consent')
            ->assertDontSee('It is free for now.');
    }

    public function test_an_owner_switches_an_unpriced_platform_on_from_the_catalogue(): void
    {
        $team = $this->company('Northwind Freight');

        $this->actingAs($team->owner)->post('/platforms/forms/switch-on', ['company' => $team->uuid])
            ->assertRedirect(route('platforms.show', ['platform' => 'forms']))
            ->assertSessionHas('status', 'Dot.Forms is switched on for Northwind Freight. Everyone in the company can open it now.');

        $held = $this->entitlement($team, 'forms');
        $this->assertSame(['active', 'unpriced_free', 'everyone'], [$held->status, $held->source, $held->access_mode]);
        $this->assertSame(['activated'], $this->events($team, 'forms'));
        $this->assertSame(1, DB::table('entitlement_outbox')->where('platform_key', 'forms')->count(), 'the platform is told');

        $this->actingAs($team->owner->fresh())->get('/platforms/forms')->assertOk()
            ->assertSee('data-state="active"', false)
            ->assertSee('Switched on, free while pricing is being finalised')
            ->assertSee('data-platform-action="open"', false);
    }

    public function test_an_administrator_may_switch_on_but_is_told_only_the_owner_can_switch_off(): void
    {
        $team = $this->company();
        $team->owner->forceFill(['name' => 'Lindiwe Dube'])->save();
        $admin = $this->memberOf($team, 'admin');

        $this->actingAs($admin)->get('/platforms/forms/switch-on')->assertOk()->assertSee('Only the owner, Lindiwe Dube, can switch it off again');
        $this->actingAs($admin)->post('/platforms/forms/switch-on', ['company' => $team->uuid])->assertRedirect(route('platforms.show', ['platform' => 'forms']));
        $this->assertSame('active', $this->entitlement($team, 'forms')->status);

        // Switching off is the owner's.
        $this->actingAs($admin)->get('/platforms/forms/switch-off')->assertForbidden();
        $this->actingAs($admin)->post('/platforms/forms/switch-off', ['company' => $team->uuid])->assertForbidden();
        $this->actingAs($admin)->get('/platforms/forms')->assertOk()->assertDontSee('Switch off Dot.Forms')->assertSee('data-platform-history', false);
        $this->assertSame('active', $this->entitlement($team, 'forms')->status);
    }

    public function test_a_member_a_manager_and_a_personal_workspace_can_switch_nothing(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'hr');

        foreach ([$this->memberOf($team), $this->memberOf($team, 'manager')] as $person) {
            $this->actingAs($person)->get('/platforms/forms/switch-on')->assertForbidden();
            $this->actingAs($person)->post('/platforms/forms/switch-on', ['company' => $team->uuid])->assertForbidden();
            $this->actingAs($person)->get('/platforms/hr/switch-off')->assertForbidden();
            $this->actingAs($person)->post('/platforms/hr/switch-off', ['company' => $team->uuid])->assertForbidden();
            $this->actingAs($person)->put('/platforms/hr/people', ['company' => $team->uuid, 'access_mode' => 'selected'])->assertForbidden();
        }

        $alone = $this->person();
        $this->actingAs($alone)->get('/platforms/forms/switch-on')->assertForbidden();
        $this->actingAs($alone)->post('/platforms/forms/switch-on', ['company' => $alone->currentTeam->uuid])->assertForbidden();

        $this->assertNull($this->entitlement($team, 'forms'));
        $this->assertSame('active', $this->entitlement($team, 'hr')->status);
        $this->assertSame('everyone', $this->entitlement($team, 'hr')->access_mode);
        $this->assertSame(1, PlatformEntitlement::count());
    }

    public function test_nobody_can_act_for_a_company_that_is_not_their_current_one(): void
    {
        $ours = $this->company('Northwind Freight');
        $theirs = $this->company('Kestrel Mining');
        $this->activatePlatforms($theirs, 'hr');
        $outsider = $ours->owner;

        // Naming the other company in the form changes nothing: the form is refused and nothing is written for either.
        $this->actingAs($outsider)->post('/platforms/forms/switch-on', ['company' => $theirs->uuid])->assertStatus(409);
        $this->actingAs($outsider)->post('/platforms/hr/switch-off', ['company' => $theirs->uuid])->assertStatus(409);
        $this->actingAs($outsider)->put('/platforms/hr/people', ['company' => $theirs->uuid, 'access_mode' => 'selected'])->assertStatus(409);
        $this->actingAs($outsider)->post('/platforms/forms/switch-on')->assertStatus(409);

        $this->assertNull($this->entitlement($ours, 'forms'));
        $this->assertNull($this->entitlement($theirs, 'forms'));
        $this->assertSame(['active', 'everyone'], [$this->entitlement($theirs, 'hr')->status, $this->entitlement($theirs, 'hr')->access_mode]);

        // A person in both companies acts only for the one they are in now.
        $both = $this->memberOf($theirs, 'admin');
        $ours->users()->attach($both, ['role' => 'admin']);
        $this->actingAs($both->fresh())->post('/platforms/forms/switch-on', ['company' => $ours->uuid])->assertStatus(409);
        $this->assertNull($this->entitlement($ours, 'forms'));
    }

    public function test_a_platform_with_a_price_cannot_be_switched_on_and_nothing_is_charged(): void
    {
        $team = $this->company();
        PlatformPlan::create(['platform_key' => 'forms', 'slug' => 'team', 'name' => 'Team', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 9900, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);

        $this->actingAs($team->owner)->get('/platforms/forms/switch-on')->assertRedirect(route('platforms.show', ['platform' => 'forms']));
        $this->actingAs($team->owner)->post('/platforms/forms/switch-on', ['company' => $team->uuid])
            ->assertRedirect(route('platforms.show', ['platform' => 'forms']))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Nothing was changed and nothing was charged'));

        $this->assertNull($this->entitlement($team, 'forms'));
    }

    public function test_the_core_and_platforms_with_their_own_sign_in_have_nothing_to_switch(): void
    {
        $team = $this->company();

        foreach (['files', 'finance', 'mines'] as $key) {
            $this->actingAs($team->owner)->get("/platforms/{$key}/switch-on")->assertRedirect(route('platforms.show', ['platform' => $key]));
            $this->actingAs($team->owner)->post("/platforms/{$key}/switch-on", ['company' => $team->uuid])->assertRedirect(route('platforms.show', ['platform' => $key]))->assertSessionHas('status');
            $this->actingAs($team->owner)->post("/platforms/{$key}/switch-off", ['company' => $team->uuid])->assertRedirect(route('platforms.show', ['platform' => $key]))->assertSessionHas('status');
        }

        $this->actingAs($team->owner)->post('/platforms/memory/switch-on', ['company' => $team->uuid])->assertNotFound();
        $this->assertSame(0, PlatformEntitlement::count());
    }

    public function test_the_owner_switches_a_platform_off_after_reading_what_that_means_and_can_switch_it_on_again(): void
    {
        $team = $this->company('Northwind Freight');
        $this->activatePlatforms($team, 'forms');

        $this->actingAs($team->owner)->get('/platforms/forms')->assertOk()->assertSee('Switch off Dot.Forms');
        $html = $this->actingAs($team->owner)->get('/platforms/forms/switch-off')->assertOk()->getContent();
        $this->assertStringContainsString('Switch off Dot.Forms for Northwind Freight?', $html);
        $this->assertStringContainsString('can no longer open Dot.Forms', $html);
        $this->assertStringContainsString('Nothing is deleted.', $html);
        $this->assertStringContainsString('data-platform-confirm="off"', $html);
        $this->assertSame('active', $this->entitlement($team, 'forms')->status, 'opening the page changes nothing');

        $this->actingAs($team->owner)->post('/platforms/forms/switch-off', ['company' => $team->uuid])
            ->assertRedirect(route('platforms.show', ['platform' => 'forms']))
            ->assertSessionHas('status', 'Dot.Forms is switched off for Northwind Freight. Nothing was deleted.');

        $this->assertSame('cancelled', $this->entitlement($team, 'forms')->status);
        $this->assertFalse($this->access()->decide($team->owner->fresh(), 'forms')->allowed);
        $this->actingAs($team->owner)->get('/platforms/forms/switch-off')->assertRedirect(route('platforms.show', ['platform' => 'forms']));

        // The history stays and the platform can come back.
        $this->actingAs($team->owner)->get('/platforms/forms')->assertOk()->assertSee('Switched off')->assertSee('data-platform-action="switch_on"', false);
        $this->actingAs($team->owner)->post('/platforms/forms/switch-on', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame(['activated', 'deactivated', 'activated'], $this->events($team, 'forms'));
        $this->assertSame(1, PlatformEntitlement::where('team_id', $team->id)->count(), 'one entitlement row, never a second');
    }

    public function test_only_what_the_company_switched_on_for_free_can_be_switched_off_here(): void
    {
        $team = $this->company();
        PlatformEntitlement::create(['team_id' => $team->id, 'platform_key' => 'hr', 'status' => 'active', 'source' => 'complimentary', 'access_mode' => 'everyone', 'started_at' => now()]);

        $this->actingAs($team->owner)->get('/platforms/hr')->assertOk()->assertDontSee('Switch off Dot.HR');
        $this->actingAs($team->owner)->get('/platforms/hr/switch-off')->assertRedirect(route('platforms.show', ['platform' => 'hr']));
        $this->actingAs($team->owner)->post('/platforms/hr/switch-off', ['company' => $team->uuid])->assertRedirect()->assertSessionHas('status', 'Only a platform the company switched on for free can be switched off here.');
        $this->assertSame('active', $this->entitlement($team, 'hr')->status);
    }

    public function test_the_owner_or_an_administrator_chooses_everyone_or_selected_people_and_each_change_is_recorded(): void
    {
        $team = $this->company('Northwind Freight');
        $admin = $this->memberOf($team, 'admin', ['name' => 'Ayanda Admin']);
        $thabo = $this->memberOf($team, 'member', ['name' => 'Thabo Mokoena']);
        $naledi = $this->memberOf($team, 'member', ['name' => 'Naledi Khumalo']);
        $outsider = $this->person(['name' => 'Olwethu Outside']);
        $this->activatePlatforms($team, 'forms');

        $form = $this->actingAs($admin)->get('/platforms/forms')->assertOk()->getContent();
        $this->assertStringContainsString('Everyone in Northwind Freight', $form);
        $this->assertStringContainsString('Thabo Mokoena', $form);
        $this->assertStringContainsString('Administrator, always', $form);
        $this->assertStringNotContainsString('Olwethu Outside', $form);

        // Selected: Thabo, and (ignored) a person outside the company, the owner and an administrator.
        $this->actingAs($admin)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$thabo->id, $outsider->id, $team->user_id, $admin->id, 'x']])
            ->assertRedirect(route('platforms.show', ['platform' => 'forms']).'#people')
            ->assertSessionHas('status', 'Dot.Forms is open to the owner, administrators and the 1 person you chose.');

        $this->assertSame('selected', $this->entitlement($team, 'forms')->access_mode);
        $this->assertSame([$thabo->id], PlatformUserAccess::where('team_id', $team->id)->where('platform_key', 'forms')->pluck('user_id')->all());
        $this->assertTrue($this->access()->decide($thabo->fresh(), 'forms')->allowed);
        $this->assertFalse($this->access()->decide($naledi->fresh(), 'forms')->allowed);
        $this->assertTrue($this->access()->decide($admin->fresh(), 'forms')->allowed, 'administrators always');
        $this->assertTrue($this->access()->decide($team->owner->fresh(), 'forms')->allowed, 'the owner always');

        // Swap Thabo for Naledi.
        $this->actingAs($team->owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$naledi->id]])->assertRedirect();
        $this->assertSame([$naledi->id], PlatformUserAccess::where('team_id', $team->id)->where('platform_key', 'forms')->pluck('user_id')->all());

        // Back to everyone.
        $this->actingAs($team->owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'everyone'])
            ->assertRedirect()->assertSessionHas('status', 'Everyone in Northwind Freight can open Dot.Forms.');
        $this->assertTrue($this->access()->decide($thabo->fresh(), 'forms')->allowed);

        $this->assertSame(['activated', 'access_mode_changed', 'user_granted', 'user_granted', 'user_revoked', 'access_mode_changed'], $this->events($team, 'forms'));

        $history = $this->actingAs($team->owner)->get('/platforms/forms')->assertOk()->getContent();
        foreach (['Limited to selected people', 'Added Thabo Mokoena', 'Removed Thabo Mokoena', 'Added Naledi Khumalo', 'Opened to everyone in the company', 'Ayanda Admin'] as $line) {
            $this->assertStringContainsString($line, $history);
        }

        $this->actingAs($admin)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'nobody'])->assertStatus(422);
    }

    public function test_people_cannot_be_chosen_for_a_platform_the_company_does_not_hold(): void
    {
        $team = $this->company();

        $this->actingAs($team->owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected'])
            ->assertRedirect()->assertSessionHas('status', 'The company has not switched this platform on.');
        $this->assertSame(0, PlatformEntitlement::count());
        $this->actingAs($team->owner)->get('/platforms/forms')->assertOk()->assertDontSee('Save who may open it');
        $this->actingAs($team->owner)->get('/platforms/files')->assertOk()->assertDontSee('Save who may open it')->assertSee('The free core is always open to the whole company.');
    }

    public function test_the_owner_and_administrators_are_told_in_infodot_when_the_entitlement_changes(): void
    {
        $team = $this->company('Northwind Freight');
        $admin = $this->memberOf($team, 'admin');
        $second = $this->memberOf($team, 'admin');
        $member = $this->memberOf($team);
        $owner = $team->owner;
        $kinds = fn (User $person) => UserNotice::where('user_id', $person->id)->where('kind', UserNotice::PLATFORM_ENTITLEMENT)->orderBy('id')->pluck('ref')->map(fn (string $ref) => implode(':', array_slice(explode(':', $ref), 2)))->all();

        // An administrator switches on: the owner and the other administrator are told, not the one who did it, not a member.
        $this->actingAs($admin)->post('/platforms/forms/switch-on', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame(['forms:activated'], $kinds($owner));
        $this->assertSame(['forms:activated'], $kinds($second));
        $this->assertSame([], $kinds($admin));
        $this->assertSame([], $kinds($member));

        // Choosing people tells of the change of mode once, and of nobody's individual permission.
        $this->actingAs($owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$member->id]])->assertRedirect();
        $this->assertSame(['forms:access_mode_changed'], $kinds($admin));
        $this->assertSame([], $kinds($member));

        // A platform administrator's act reaches everyone who runs the company, the owner included.
        $staff = $this->administrator();
        $this->entitlements()->suspend($team, 'forms', $staff);
        $this->assertSame(['forms:activated', 'forms:suspended'], $kinds($owner));
        $this->entitlements()->restore($team, 'forms', $staff);
        $this->actingAs($owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'everyone'])->assertRedirect();
        $this->actingAs($owner)->post('/platforms/forms/switch-off', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame(['forms:access_mode_changed', 'forms:suspended', 'forms:restored', 'forms:access_mode_changed', 'forms:deactivated'], $kinds($admin));

        // A person who switched ordinary notices off is still told: this is about what their company holds.
        $quiet = $this->memberOf($team, 'admin', ['notices_in_app' => false]);
        $this->actingAs($owner)->post('/platforms/forms/switch-on', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame(['forms:activated'], $kinds($quiet));

        // The words, on the notifications page, name the platform and the company and link to the platform's page.
        $notices = app(InAppNotices::class);
        $first = UserNotice::where('user_id', $owner->id)->where('kind', UserNotice::PLATFORM_ENTITLEMENT)->orderBy('id')->first();
        $said = $notices->describe($first);
        $this->assertStringContainsString('Dot.Forms was switched on for', $said['text']);
        $this->assertStringContainsString('Northwind Freight', $said['text']);
        $this->assertSame(route('platforms.show', ['platform' => 'forms']), $said['url']);
        $this->assertSame('Platforms', InAppNotices::label(UserNotice::PLATFORM_ENTITLEMENT));
        $this->actingAs($owner)->get(route('notices'))->assertOk()->assertSee('Dot.Forms was switched on for')->assertSee('paused');
    }
}
