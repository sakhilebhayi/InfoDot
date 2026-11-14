<?php

namespace Tests\Feature\Platforms\Scenarios;

use App\Livewire\DotSwitcher;
use App\Models\UserNotice;
use App\Services\Platforms\AccessDecision;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** Brief section 15, scenarios 9, 10, 12, 15 and 16: InfoDot staff, recommendations, and who inside a company gets in. */
class PeopleAndAccessScenariosTest extends ScenarioTestCase
{
    public function test_scenario_09_an_administrator_granting_complimentary_access(): void
    {
        $staff = $this->administrator();
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->assertCannotOpen($employee, 'projects', AccessDecision::NOT_ACTIVATED);

        // A moderator or the company's owner cannot do this; a platform administrator can, and must say why.
        $form = ['platform' => 'projects', 'reason' => self::WHY, 'until' => now()->addDays(30)->toDateString()];
        $this->actingAs($this->moderator())->post('/admin/platforms/companies/'.$team->uuid.'/grant', $form)->assertForbidden();
        $this->actingAs($team->owner)->post('/admin/platforms/companies/'.$team->uuid.'/grant', $form)->assertForbidden();
        $this->actingAs($staff)->post('/admin/platforms/companies/'.$team->uuid.'/grant', ['reason' => ''] + $form)->assertSessionHasErrors('reason');
        $this->assertNull($this->held($team, 'projects'));

        $this->actingAs($staff)->post('/admin/platforms/companies/'.$team->uuid.'/grant', $form)->assertRedirect()->assertSessionHasNoErrors();

        $entitlement = $this->held($team, 'projects');
        $this->assertSame(['active', 'complimentary', $staff->id], [$entitlement->status, $entitlement->source, $entitlement->granted_by]);
        $this->assertCanOpen($employee, 'projects');
        $this->assertTrue($this->lastTold($team, 'projects')['entitled']);
        $this->assertSame(['complimentary_granted'], $this->history($team, 'projects'));
        $this->assertSame(1, DB::table('platform_catalogue_events')->where('actor_id', $staff->id)->count() + DB::table('platform_entitlement_events')->where('actor_id', $staff->id)->count(), 'the grant is in the audit trail with who did it');
        $this->assertSame(1, UserNotice::where('user_id', $team->owner->id)->where('kind', UserNotice::PLATFORM_ENTITLEMENT)->count(), 'the owner is told');
        $this->actingAs($team->owner)->get('/platforms/projects')->assertSee('given to Northwind Freight at no charge');

        // It ends on its date, and nothing is charged at any point.
        $this->travel(31)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertCannotOpen($employee, 'projects', AccessDecision::EXPIRED);
        $this->assertSame(0, DB::table('platform_payment_confirmations')->count());
    }

    public function test_scenario_10_an_employee_attempting_to_access_an_unauthorised_application(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $manager = $this->memberOf($team, 'manager');

        // Not switched on: refused, told whom to ask, and cannot switch it on themselves, whatever they send.
        $this->open($employee, 'forms')->assertForbidden()->assertSee('Ask '.$team->owner->name)->assertDontSee('Switch on Dot.Forms');
        foreach ([$employee, $manager] as $person) {
            $this->switchOn($team, $person, 'forms')->assertForbidden();
            $this->actingAs($person)->post('/platforms/forms/activate', ['company' => $team->uuid])->assertForbidden();
        }
        $this->assertNull($this->held($team, 'forms'));

        // Switched on for selected people only: the employee who is not chosen is refused, the chosen one is not.
        $chosen = $this->memberOf($team);
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->actingAs($team->owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$chosen->id]])->assertRedirect();
        $this->assertCannotOpen($employee, 'forms', AccessDecision::NOT_PERMITTED_FOR_YOU);
        $this->open($employee, 'forms')->assertSee('You have not been added to Dot.Forms');
        $this->assertCanOpen($chosen, 'forms');

        // Nor can they widen their own access or reach another company's platforms.
        $this->actingAs($employee)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'everyone'])->assertForbidden();
        $other = $this->company('Kestrel Mining');
        $this->switchOn($other, $other->owner, 'hr')->assertRedirect();
        $this->assertCannotOpen($employee, 'hr', AccessDecision::NOT_ACTIVATED);
        $this->assertCannotOpen($this->person(), 'hr', AccessDecision::NO_PERSONAL_ACCESS);
    }

    public function test_scenario_12_a_business_receiving_a_relevant_platform_recommendation(): void
    {
        $team = $this->company('Northwind Freight');
        $this->assertStringNotContainsString('Suggested for', (string) $this->actingAs($team->owner)->get('/platforms')->assertOk()->getContent(), 'a company with nothing true to say is shown nothing');

        // Five people on InfoDot: the rule for Dot.HR is true, and the page says why in checkable words.
        foreach (range(1, 4) as $i) {
            $this->memberOf($team);
        }
        $page = $this->actingAs($team->owner)->get('/platforms')->assertOk();
        $page->assertSee('Suggested for Northwind Freight')->assertSee('data-recommendation="hr"', false)
            ->assertSee('Northwind Freight has 5 people on InfoDot. Dot.HR keeps their records, leave and timesheets in one place.');

        // Browsing and switching on work as before; once the company holds the platform the suggestion is gone.
        $this->switchOn($team, $team->owner, 'hr')->assertRedirect();
        $this->assertStringNotContainsString('data-recommendation="hr"', (string) $this->actingAs($team->owner)->get('/platforms')->getContent());

        // The industry the company chose is a real fact too; one that was never recorded produces nothing.
        $mine = $this->company('Kestrel Mining');
        $mine->forceFill(['industry' => 'mining'])->save();
        $this->actingAs($mine->owner)->get('/platforms')->assertSee('registered on InfoDot as a mining and quarrying business');
    }

    public function test_scenario_15_a_business_with_different_permissions_across_multiple_employees(): void
    {
        $team = $this->company('Northwind Freight');
        $thandi = $this->memberOf($team);
        $sipho = $this->memberOf($team);
        $lerato = $this->memberOf($team, 'manager');
        $admin = $this->memberOf($team, 'admin');
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->switchOn($team, $team->owner, 'hr')->assertRedirect();

        // Forms: Thandi only. HR: Sipho and Lerato. Owners and administrators always.
        $this->actingAs($team->owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$thandi->id]])->assertRedirect();
        $this->actingAs($admin)->put('/platforms/hr/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$sipho->id, $lerato->id]])->assertRedirect();

        $expected = [
            'owner' => [$team->owner, true, true], 'admin' => [$admin, true, true],
            'thandi' => [$thandi, true, false], 'sipho' => [$sipho, false, true], 'lerato' => [$lerato, false, true],
        ];
        foreach ($expected as $who => [$person, $forms, $hr]) {
            foreach (['forms' => $forms, 'hr' => $hr] as $platform => $allowed) {
                $allowed ? $this->assertCanOpen($person, $platform, "$who may open $platform") : $this->assertCannotOpen($person, $platform, AccessDecision::NOT_PERMITTED_FOR_YOU);
            }
        }

        // The platform is told the same thing when each of them arrives: the company is entitled for some people and not others.
        $this->assertSame([true], collect($this->redeemFor($thandi, 'forms')->assertOk()->json('teams'))->where('uuid', $team->uuid)->pluck('entitled')->all());
        $this->redeemFor($sipho, 'forms')->assertForbidden();

        // Changing one person's access changes only theirs, and the history says who did it.
        $this->actingAs($team->owner)->put('/platforms/forms/people', ['company' => $team->uuid, 'access_mode' => 'selected', 'people' => [$thandi->id, $sipho->id]])->assertRedirect();
        $this->assertCanOpen($sipho, 'forms');
        $this->assertCannotOpen($lerato, 'forms', AccessDecision::NOT_PERMITTED_FOR_YOU);
        $this->assertContains('user_granted', $this->history($team, 'forms'));
    }

    public function test_scenario_16_a_business_switching_between_two_activated_platforms(): void
    {
        $team = $this->company('Northwind Freight');
        $person = $this->memberOf($team);
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->switchOn($team, $team->owner, 'hr')->assertRedirect();

        // Dot Apps lists exactly the two the company holds, beside the core, and opens each in turn.
        $bays = collect(Livewire::actingAs($person->fresh())->test(DotSwitcher::class)->instance()->groups)->flatMap(fn (array $group) => array_keys($group['members']))->all();
        $this->assertEqualsCanonicalizing(['files', 'docs', 'tasks', 'finance', 'forms', 'hr'], $bays);

        $this->actingAs($person->fresh());
        $first = $this->get('/go/forms')->assertOk();
        $second = $this->get('/go/hr')->assertOk();
        $this->assertSame(2, DB::table('personal_access_tokens')->count(), 'one launch code for each, made when each was opened');
        $this->assertSame(['ecosystem-handoff:forms', 'ecosystem-handoff:hr'], DB::table('personal_access_tokens')->orderBy('id')->pluck('name')->all(), 'a code opens only the platform it was made for');
        $this->assertNotSame($first->getContent(), $second->getContent());

        // Back and forth: the company is the same company throughout.
        foreach (['forms', 'hr', 'forms'] as $key) {
            $this->assertSame($team->id, $this->access()->decide($person->fresh(), $key)->team->id);
        }

        $this->markTestIncomplete('Landing inside each platform, with the sign-in and company carried over, happens in that platform and is not exercised from InfoDot.');
    }
}
