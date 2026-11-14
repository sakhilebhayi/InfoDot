<?php

namespace Tests\Feature\Platforms\Scenarios;

use App\Models\PlatformCatalogue;
use App\Models\Questions;
use App\Services\Platforms\AccessDecision;
use Illuminate\Support\Facades\DB;

/** Brief section 15, scenarios 18 and 20: a platform that is down for a while, and what a company keeps when it stops using one. */
class AvailabilityAndDataScenariosTest extends ScenarioTestCase
{
    public function test_scenario_18_a_platform_becoming_temporarily_unavailable(): void
    {
        $staff = $this->administrator();
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->assertCanOpen($employee, 'forms');
        $held = $this->held($team, 'forms')->only(['status', 'source', 'access_mode']);

        // An administrator sets it to maintenance, with a reason. Nobody's entitlement changes.
        $this->actingAs($staff)->post('/admin/platforms/catalogue/forms', ['reason' => self::WHY, 'operational_status' => 'maintenance', 'discoverable' => 1])->assertRedirect();

        DB::table('personal_access_tokens')->delete();
        $this->assertCannotOpen($employee, 'forms', AccessDecision::TEMPORARILY_UNAVAILABLE);
        $this->open($employee, 'forms')->assertSee('Dot.Forms is temporarily unavailable')->assertSee('Nothing is wrong with your account');
        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'no launch code is made for a platform that is down');
        $this->actingAs($team->owner)->get('/platforms/forms')->assertSee('Temporarily unavailable');
        $this->assertSame($held, $this->held($team, 'forms')->only(['status', 'source', 'access_mode']), 'the company still holds it');
        $this->redeemFor($employee, 'forms')->assertForbidden();

        // A company that never switched it on is told the true thing too, and is not asked to pay or activate while it is down.
        $other = $this->company('Kestrel Mining');
        $this->assertSame('temporarily_unavailable', $this->access()->businessState($other, 'forms'));

        // Set back to operational, it opens again with nothing else done.
        $this->actingAs($staff)->post('/admin/platforms/catalogue/forms', ['reason' => self::WHY, 'operational_status' => 'operational', 'discoverable' => 1])->assertRedirect();
        $this->assertCanOpen($employee, 'forms');

        // A degraded platform still opens; one that is not launched does not, with its own words.
        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'degraded']);
        $this->assertCanOpen($employee, 'forms');
        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'not_launched']);
        $this->assertCannotOpen($employee, 'forms', AccessDecision::NOT_LAUNCHED);
    }

    public function test_scenario_20_a_business_retaining_access_to_its_own_data_after_cancellation(): void
    {
        $team = $this->company('Northwind Freight');
        $employee = $this->memberOf($team);
        $question = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $employee->id, 'team_id' => $team->id, 'visibility' => 'company', 'question' => 'How do we onboard a supplier?', 'description' => 'details', 'status' => 0]));
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->assertCanOpen($employee, 'forms');

        // The owner switches the platform off.
        $this->actingAs($team->owner)->get('/platforms/forms/switch-off')->assertOk()->assertSee('Nothing is deleted');
        $this->actingAs($team->owner)->post('/platforms/forms/switch-off', ['company' => $team->uuid])->assertRedirect()->assertSessionHas('status', 'Dot.Forms is switched off for Northwind Freight. Nothing was deleted.');
        $this->assertCannotOpen($employee, 'forms', AccessDecision::CANCELLED);

        // InfoDot keeps everything of the company's: its content here, the entitlement row, and the whole history.
        $this->assertNotNull(Questions::withoutGlobalScopes()->find($question->id));
        $this->actingAs($employee)->get(route('questions.view', ['qid' => $question->id]))->assertOk()->assertSee('How do we onboard a supplier?');
        $this->assertSame(['activated', 'deactivated'], $this->history($team, 'forms'));
        $this->assertSame('cancelled', $this->held($team, 'forms')->status);
        $this->actingAs($team->owner)->get('/platforms/forms')->assertSee('Switched off');

        // And it never tells the platform to delete anything: the only message is the new entitlement state.
        $messages = DB::table('entitlement_outbox')->where('team_uuid', $team->uuid)->where('platform_key', 'forms')->orderBy('id')->get()->map(fn ($row) => json_decode($row->payload, true));
        $this->assertSame(['activated', 'deactivated'], $messages->pluck('type')->all());
        $this->assertSame([true, false], $messages->pluck('entitled')->all());
        foreach ($messages as $message) {
            $this->assertEmpty(array_intersect(array_keys($message), ['delete', 'purge', 'erase', 'wipe']));
        }

        // Switching it on again brings back the same entitlement, with its history, at once.
        $this->switchOn($team, $team->owner, 'forms')->assertRedirect();
        $this->assertCanOpen($employee, 'forms');
        $this->assertSame(1, DB::table('platform_entitlements')->where('team_id', $team->id)->where('platform_key', 'forms')->count());

        $this->markTestIncomplete('What the platform itself keeps and lets the company read after cancellation (read-only access and export) is that platform\'s own behaviour; InfoDot only sends the state and never a delete.');
    }
}
