<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogueEvent;
use App\Models\Team;
use App\Services\Ops\OpsCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The admin centre's records (R-50 section 9): the outbox with retry, the payments Dot.Finance confirmed, and the
 * checks that say when a row was written around the services.
 */
class AdminRecordsTest extends AdminCentreTestCase
{
    public function test_the_outbox_shows_each_state_and_a_parked_event_can_be_retried(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $this->entitlements()->grantComplimentary($team, 'hr', $admin, null, self::WHY);
        $row = DB::table('entitlement_outbox')->first();

        $this->actingAs($admin)->get('/admin/platforms/outbox')->assertOk()->assertSee($row->event_id)->assertSee($team->name)->assertSee('Not tried yet');
        $this->actingAs($admin)->get('/admin/platforms/outbox?state=parked')->assertOk()->assertSee('Nothing is parked')->assertDontSee($row->event_id);
        $this->actingAs($admin)->get('/admin/platforms/outbox?state=nonsense')->assertSessionHasErrors('state');

        DB::table('entitlement_outbox')->where('id', $row->id)->update(['failed_at' => now(), 'attempts' => 25, 'last_error' => 'HTTP 422', 'next_attempt_at' => now()->addHours(6)]);
        $this->actingAs($admin)->get('/admin/platforms/outbox?state=parked')->assertOk()->assertSee($row->event_id)->assertSee('HTTP 422');

        $this->actingAs($admin)->post('/admin/platforms/outbox/'.$row->id.'/retry', ['state' => 'parked'])->assertRedirect('/admin/platforms/outbox?state=parked')->assertSessionHas('status');

        $after = DB::table('entitlement_outbox')->where('id', $row->id)->first();
        $this->assertSame([null, 0], [$after->failed_at, (int) $after->attempts]);
        $this->assertFalse(now()->lt($after->next_attempt_at), 'the event is due now');

        $audit = PlatformCatalogueEvent::where('type', 'delivery_retried')->sole();
        $this->assertSame([$admin->id, 'outbox', $row->event_id], [$audit->actor_id, $audit->subject_type, $audit->subject_key]);

        // The retried event really goes out on the next flush.
        config(['ecosystem.service_secrets.hr' => Str::random(40)]);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->artisan('platforms:flush-outbox')->expectsOutput('Delivered 1 event(s).')->assertSuccessful();

        // A delivered event is left alone, and an unknown one is not found.
        $this->actingAs($admin)->post('/admin/platforms/outbox/'.$row->id.'/retry')->assertSessionHas('status', 'That event has already been delivered. Nothing was changed.');
        $this->assertNotNull(DB::table('entitlement_outbox')->where('id', $row->id)->value('sent_at'));
        $this->actingAs($admin)->post('/admin/platforms/outbox/999999/retry')->assertNotFound();
        $this->actingAs($admin)->get('/admin/platforms/outbox?state=delivered')->assertOk()->assertSee($row->event_id)->assertDontSee('Retry now');
    }

    public function test_the_payments_page_is_empty_until_dot_finance_confirms_and_counts_only_what_it_confirmed(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $this->plan('hr', [], true, $admin);
        $key = $this->financeKey();

        // Admin grants and trials are not revenue.
        $this->entitlements()->grantComplimentary($team, 'forms', $admin, null, self::WHY);
        $this->actingAs($admin)->get('/admin/platforms/payments')->assertOk()->assertSee('Dot.Finance has not confirmed any payment yet')->assertDontSee('ZAR');

        $this->deliver($this->paymentEvent($team), $key)->assertOk();
        $this->deliver($this->paymentEvent($team, 'renewed'), $key)->assertOk();
        $this->deliver($this->paymentEvent($team, 'refunded', ['amount_minor' => 10000]), $key)->assertOk();

        $this->actingAs($admin)->get('/admin/platforms/payments')->assertOk()
            ->assertSee('ZAR 498.00')->assertSee('2 payments')->assertSee('ZAR 100.00 was refunded')->assertSee($team->name)->assertSee('SUB-1001');
    }

    public function test_ops_check_reports_a_paid_entitlement_with_no_confirmation_and_a_plan_on_sale_with_no_approval_record(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $plan = $this->plan('hr', [], true, $admin);
        $key = $this->financeKey();
        $this->deliver($this->paymentEvent($team), $key)->assertOk();
        $this->entitlements()->subscribe($this->company('Waiting Yard'), 'hr', $plan, Team::where('name', 'Waiting Yard')->firstOrFail()->owner);

        $found = app(OpsCheck::class)->run();
        $this->assertSame([0, 0, 0], [$found['paid_entitlement_without_confirmation'], $found['plan_on_sale_without_approval_record'], $found['entitlements_overdue_1h']]);

        // Rows written around the services, as a mistaken script or a hand edit would.
        $other = $this->company('Second Yard');
        DB::table('platform_entitlements')->insert(['team_id' => $other->id, 'platform_key' => 'hr', 'status' => 'active', 'source' => 'paid', 'access_mode' => 'everyone', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('platform_plans')->insert(['platform_key' => 'forms', 'slug' => 'hand', 'name' => 'Hand made', 'model' => 'subscription', 'approved_at' => now(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('platform_bundles')->insert(['slug' => 'hand', 'name' => 'Hand made', 'approved_at' => now(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('platform_entitlements')->insert(['team_id' => $other->id, 'platform_key' => 'forms', 'status' => 'trial', 'source' => 'trial', 'access_mode' => 'everyone', 'trial_ends_at' => now()->subHours(3), 'created_at' => now(), 'updated_at' => now()]);

        $found = app(OpsCheck::class)->run();
        $this->assertSame([1, 2, 1], [$found['paid_entitlement_without_confirmation'], $found['plan_on_sale_without_approval_record'], $found['entitlements_overdue_1h']]);

        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertSame(0, app(OpsCheck::class)->run()['entitlements_overdue_1h']);
    }
}
