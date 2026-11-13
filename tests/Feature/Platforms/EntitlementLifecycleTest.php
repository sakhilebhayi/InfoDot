<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Services\Platforms\EntitlementRefused;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * Every transition in the lifecycle table (R-50 section 6) that this step adds, at the service that makes it, and the
 * scheduled command that ends what time has ended. The payment transitions are in PlatformSubscriptionEventsTest.
 */
class EntitlementLifecycleTest extends AdminCentreTestCase
{
    public function test_only_a_platform_administrator_has_the_commercial_powers(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'hr');
        $plan = $this->plan('forms', ['trial_days' => 14]);
        $entitlements = $this->entitlements();
        $refused = 0;

        foreach ([$this->moderator(), $this->memberOf($team), $this->memberOf($team, 'admin'), $team->owner] as $person) {
            foreach ([
                fn () => $entitlements->grantComplimentary($team, 'projects', $person, null, self::WHY),
                fn () => $entitlements->suspend($team, 'hr', $person, self::WHY),
                fn () => $entitlements->restore($team, 'hr', $person, self::WHY),
                fn () => $entitlements->administerAccessMode($team, 'hr', 'selected', $person, self::WHY),
                fn () => $entitlements->startTrial($team, 'projects', $person, null, 30, self::WHY), // days are an administrator's to give
            ] as $attempt) {
                try {
                    $attempt();
                } catch (AuthorizationException|EntitlementRefused) {
                    $refused++;
                }
            }
        }

        $this->assertSame(20, $refused);

        // A moderator and a plain member cannot cancel or start a plan trial either; only the owner cancels.
        foreach ([$this->moderator(), $this->memberOf($team)] as $person) {
            foreach ([fn () => $entitlements->cancel($team, 'hr', $person), fn () => $entitlements->startTrial($team, 'forms', $person, $plan), fn () => $entitlements->subscribe($team, 'forms', $plan, $person)] as $attempt) {
                try {
                    $attempt();
                    $this->fail('A person without the power changed an entitlement.');
                } catch (AuthorizationException) {
                    $this->addToAssertionCount(1);
                }
            }
        }

        $this->assertSame(['activated'], PlatformEntitlementEvent::pluck('type')->all());
        $this->assertSame(1, PlatformEntitlement::count());
    }

    public function test_a_company_starts_the_trial_a_plan_offers_once(): void
    {
        $team = $this->company();
        $owner = $team->owner;
        $plan = $this->plan('hr', ['trial_days' => 14]);
        $noTrial = $this->plan('forms');

        try {
            $this->entitlements()->startTrial($team, 'forms', $owner, $noTrial);
            $this->fail('A trial started on a plan that offers none.');
        } catch (EntitlementRefused) {
            $this->assertNull($this->access()->entitlement($team, 'forms'));
        }

        $entitlement = $this->entitlements()->startTrial($team, 'hr', $this->memberOf($team, 'admin'), $plan);
        $this->assertSame(['trial', 'trial', $plan->id], [$entitlement->status, $entitlement->source, $entitlement->plan_id]);
        $this->assertTrue($this->access()->decideFor($owner, $team, 'hr')->allowed);
        $this->assertSame('trial', $this->access()->businessState($team, 'hr'));

        $this->travel(15)->days();
        $this->assertSame('trial_ended', $this->access()->decideFor($owner, $team, 'hr')->reason);
        $this->artisan('platforms:lifecycle')->assertSuccessful();

        $this->expectException(EntitlementRefused::class);
        $this->entitlements()->startTrial($team, 'hr', $owner, $plan);
    }

    public function test_a_trial_ends_as_payment_required_with_a_priced_plan_and_as_expired_without_one(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $plan = $this->plan('hr', ['trial_days' => 14], true, $admin);
        $this->entitlements()->startTrial($team, 'hr', $admin, $plan, null, self::WHY);
        $this->entitlements()->startTrial($team, 'forms', $admin, null, 7, self::WHY);
        DB::table('entitlement_outbox')->delete();

        $this->artisan('platforms:lifecycle')->expectsOutputToContain('Ended 0 trial(s)')->assertSuccessful();
        $this->assertSame(0, DB::table('entitlement_outbox')->count());

        $this->travel(8)->days();
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('Ended 1 trial(s)')->assertSuccessful();
        $this->assertSame(['trial', 'expired'], [$this->access()->entitlement($team, 'hr')->status, $this->access()->entitlement($team, 'forms')->status]);

        $this->travel(7)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $hr = $this->access()->entitlement($team, 'hr');
        $this->assertSame(['payment_required', null], [$hr->status, $hr->grace_until]);
        $this->assertSame('payment_required', $this->access()->decideFor($team->owner, $team, 'hr')->reason);
        $this->assertSame('expired', $this->access()->decideFor($team->owner, $team, 'forms')->reason);

        // Idempotent: nothing more happens, however often it runs.
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('Ended 0 trial(s), 0 dated grant(s), 0 grace period(s) and 0 paid period(s).')->assertSuccessful();
        $this->artisan('platforms:lifecycle')->assertSuccessful();

        $ended = PlatformEntitlementEvent::where('type', 'trial_ended')->orderBy('id')->get();
        $this->assertSame([['trial', 'expired'], ['trial', 'payment_required']], $ended->map(fn ($e) => [$e->from_status, $e->to_status])->all());
        $this->assertSame([null, null], $ended->pluck('actor_id')->all());
        $this->assertSame(2, DB::table('entitlement_outbox')->count(), 'one message to each platform, once');
        $this->assertFalse(json_decode(DB::table('entitlement_outbox')->where('platform_key', 'forms')->value('payload'), true)['entitled']);
    }

    public function test_a_dated_complimentary_grant_expires_on_its_date_and_an_open_one_never_does(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $this->entitlements()->grantComplimentary($team, 'hr', $admin, now()->addDays(10), self::WHY);
        $this->entitlements()->grantComplimentary($team, 'forms', $admin, null, self::WHY);

        $this->travel(9)->days();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertSame('active', $this->access()->entitlement($team, 'hr')->status);

        $this->travel(2)->days();
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('1 dated grant(s)')->assertSuccessful();
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('0 dated grant(s)')->assertSuccessful();

        $this->assertSame(['expired', 'active'], [$this->access()->entitlement($team, 'hr')->status, $this->access()->entitlement($team, 'forms')->status]);
        $this->assertSame(1, PlatformEntitlementEvent::where('type', 'complimentary_ended')->count());
        $this->assertSame('subscription_expired', $this->access()->businessState($team, 'hr'));

        $this->travel(5)->years();
        $this->artisan('platforms:lifecycle')->assertSuccessful();
        $this->assertTrue($this->access()->entitlement($team, 'forms')->grants());
    }

    public function test_the_owner_asks_for_a_paid_plan_and_nothing_opens_until_it_is_paid(): void
    {
        $team = $this->company();
        $plan = $this->plan('hr');

        $entitlement = $this->entitlements()->subscribe($team, 'hr', $plan, $team->owner);
        $this->assertSame(['pending', 'paid', $plan->id], [$entitlement->status, $entitlement->source, $entitlement->plan_id]);
        $this->assertSame('payment_required', $this->access()->decideFor($team->owner, $team, 'hr')->reason);
        $this->assertSame('activation_pending', $this->access()->businessState($team, 'hr'));

        // Asking twice is one request; an unapproved or free plan cannot be asked for.
        $this->entitlements()->subscribe($team, 'hr', $plan, $team->owner);
        $this->assertSame(1, PlatformEntitlementEvent::count());

        foreach ([$this->plan('forms', [], false), $this->plan('projects', ['model' => 'free', 'amount_minor' => 0])] as $notForSale) {
            try {
                $this->entitlements()->subscribe($team, $notForSale->platform_key, $notForSale, $team->owner);
                $this->fail('A plan that is not for sale was asked for.');
            } catch (EntitlementRefused) {
                $this->assertNull($this->access()->entitlement($team, $notForSale->platform_key));
            }
        }

        // The owner can withdraw the request.
        $this->assertSame('cancelled', $this->entitlements()->cancel($team, 'hr', $team->owner)->status);
    }

    public function test_asking_for_a_paid_plan_during_a_trial_keeps_the_trial(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $plan = $this->plan('hr', [], true, $admin);
        $this->entitlements()->startTrial($team, 'hr', $admin, null, 14, self::WHY);

        $entitlement = $this->entitlements()->subscribe($team, 'hr', $plan, $team->owner);

        $this->assertSame(['trial', $plan->id], [$entitlement->status, $entitlement->plan_id]);
        $this->assertTrue($this->access()->decideFor($team->owner, $team, 'hr')->allowed);
    }

    public function test_cancelling_ends_free_access_now_and_a_cancelled_platform_can_come_back(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $this->entitlements()->grantComplimentary($team, 'hr', $admin, null, self::WHY);

        $cancelled = $this->entitlements()->cancel($team, 'hr', $admin, self::WHY);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->ended_at);
        $this->assertFalse($cancelled->grants());

        $this->entitlements()->cancel($team, 'hr', $admin, self::WHY);
        $this->assertSame(1, PlatformEntitlementEvent::where('type', 'cancelled')->count(), 'cancelling twice is one event');

        // Unpriced, so the owner can simply switch it on again; the history is kept.
        $this->assertSame('active', $this->entitlements()->activate($team, 'hr', $team->owner)->status);
        $this->assertSame(['complimentary_granted', 'cancelled', 'activated'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_a_suspended_platform_cannot_be_trialled_or_asked_for_and_only_an_administrator_cancels_it(): void
    {
        $admin = $this->administrator();
        $team = $this->company();
        $this->activatePlatforms($team, 'hr');
        $plan = $this->plan('hr', ['trial_days' => 14], true, $admin);
        $this->entitlements()->suspend($team, 'hr', $admin, self::WHY);

        foreach ([
            fn () => $this->entitlements()->startTrial($team, 'hr', $admin, null, 14, self::WHY),
            fn () => $this->entitlements()->subscribe($team, 'hr', $plan, $team->owner),
            fn () => $this->entitlements()->cancel($team, 'hr', $team->owner),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A suspension was stepped around.');
            } catch (EntitlementRefused) {
                $this->assertSame('suspended', $this->access()->entitlement($team, 'hr')->status);
            }
        }

        $this->assertSame('cancelled', $this->entitlements()->cancel($team, 'hr', $admin, self::WHY)->status);
    }

    public function test_the_lifecycle_command_runs_every_minute_and_survives_a_second_run_in_the_same_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'platforms:lifecycle'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);

        $admin = $this->administrator();

        foreach (['Yard One', 'Yard Two', 'Yard Three'] as $name) {
            $this->entitlements()->startTrial($this->company($name), 'hr', $admin, null, 1, self::WHY);
        }

        $this->travel(2)->days();
        $first = $this->entitlements()->runLifecycle();
        $second = $this->entitlements()->runLifecycle();

        $this->assertSame([3, 0], [$first['trials'], $second['trials']]);
        $this->assertSame(3, PlatformEntitlementEvent::where('type', 'trial_ended')->count());
        $this->assertSame(3, PlatformEntitlement::where('status', 'expired')->whereNotNull('ended_at')->count());
    }
}
