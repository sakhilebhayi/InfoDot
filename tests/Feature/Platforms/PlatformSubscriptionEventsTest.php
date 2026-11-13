<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPaymentConfirmation;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Services\Platforms\CatalogueAdmin;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Finder\Finder;

/**
 * POST /api/finance/platform-subscription-events (R-50 section 6): the only door through which a platform becomes
 * paid. Signed, time-limited, applied once per event id, silent about why it refuses, and shut while the key is unset.
 */
class PlatformSubscriptionEventsTest extends AdminCentreTestCase
{
    private Team $team;

    private PlatformPlan $plan;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->company();
        $this->plan = $this->plan('hr');
        $this->key = $this->financeKey();
    }

    private function entitlement(string $platform = 'hr'): ?PlatformEntitlement
    {
        return $this->access()->entitlement($this->team, $platform);
    }

    public function test_a_signed_paid_event_makes_the_platform_active_and_paid(): void
    {
        $this->entitlements()->subscribe($this->team, 'hr', $this->plan, $this->team->owner);
        $this->assertSame('payment_required', $this->access()->decideFor($this->team->owner, $this->team, 'hr')->reason);

        $event = $this->paymentEvent($this->team);
        $this->deliver($event, $this->key)->assertOk()->assertExactJson(['status' => 'applied']);

        $entitlement = $this->entitlement();
        $this->assertSame(['active', 'paid', $this->plan->id, 'SUB-1001'], [$entitlement->status, $entitlement->source, $entitlement->plan_id, $entitlement->reference]);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $entitlement->current_period_ends_at->timestamp, 5);
        $this->assertTrue($this->access()->decideFor($this->team->owner, $this->team, 'hr')->allowed);

        $confirmation = PlatformPaymentConfirmation::sole();
        $this->assertSame([$event['event_id'], 'paid', 24900, 'ZAR', 'applied'], [$confirmation->event_id, $confirmation->event, $confirmation->amount_minor, $confirmation->currency, $confirmation->outcome]);

        $history = PlatformEntitlementEvent::orderBy('id')->get();
        $this->assertSame(['subscription_requested', 'payment_confirmed'], $history->pluck('type')->all());
        $this->assertSame(['pending', 'active', null, $event['event_id']], [$history[1]->from_status, $history[1]->to_status, $history[1]->actor_id, $history[1]->details['confirmation']]);
        $this->assertSame(2, DB::table('entitlement_outbox')->count());
    }

    public function test_a_payment_is_honoured_even_when_the_company_never_asked_here_first(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);

        $this->assertSame(['active', 'paid'], [$this->entitlement()->status, $this->entitlement()->source]);
    }

    public function test_a_bad_signature_changes_nothing(): void
    {
        $event = $this->paymentEvent($this->team);
        $raw = (string) json_encode($event);
        $now = time();

        $attempts = [
            'another key' => $this->deliver($event, Str::random(48)),
            'no signature' => $this->call('POST', '/api/finance/platform-subscription-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_TIMESTAMP' => (string) $now], $raw),
            'no timestamp' => $this->call('POST', '/api/finance/platform-subscription-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_SIGNATURE' => Signature::sign($this->key, $now, $raw)], $raw),
            'signature of another body' => $this->deliver($event, $this->key, $now, Signature::sign($this->key, $now, str_replace('24900', '1', $raw))),
            'signature without the prefix' => $this->deliver($event, $this->key, $now, substr(Signature::sign($this->key, $now, $raw), 7)),
            'the seminar callback key' => tap(Str::random(48), fn ($other) => config(['seminars.paid.callback_secret' => $other, 'seminars.paid.enabled' => true])) === null ? null : $this->deliver($event, (string) config('seminars.paid.callback_secret')),
            'unsigned form post' => $this->post('/api/finance/platform-subscription-events', $event),
        ];

        foreach ($attempts as $what => $response) {
            $response->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        }

        $this->assertNull($this->entitlement());
        $this->assertSame(0, PlatformPaymentConfirmation::count());
    }

    public function test_a_stale_or_future_timestamp_is_refused_even_with_a_good_signature(): void
    {
        $event = $this->paymentEvent($this->team);

        $this->deliver($event, $this->key, time() - 301)->assertStatus(401);
        $this->deliver($event, $this->key, time() + 301)->assertStatus(401);
        $this->assertNull($this->entitlement());

        $this->deliver($event, $this->key, time() - 290)->assertOk();
        $this->assertSame('active', $this->entitlement()->status);
    }

    public function test_with_the_key_unset_everything_is_refused(): void
    {
        $event = $this->paymentEvent($this->team);

        foreach ([null, ''] as $unset) {
            config(['platform_subscriptions.confirmation_secret' => $unset]);

            $this->deliver($event, $this->key)->assertStatus(401);
            $this->deliver($event, '')->assertStatus(401);
            $this->deliver($event, (string) $unset)->assertStatus(401);
        }

        $this->assertNull($this->entitlement());
        $this->assertSame(0, PlatformPaymentConfirmation::count());
    }

    public function test_a_replayed_event_is_applied_once(): void
    {
        $event = $this->paymentEvent($this->team);

        $this->deliver($event, $this->key)->assertOk()->assertExactJson(['status' => 'applied']);
        $first = DB::table('platform_entitlements')->first();

        $this->travel(2)->minutes();
        $this->deliver($event, $this->key)->assertOk()->assertExactJson(['status' => 'duplicate']);
        // The same id carrying a different story is still the same event.
        $this->deliver(['event' => 'refunded', 'amount_minor' => 24900] + $event, $this->key)->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertEquals($first, DB::table('platform_entitlements')->first());
        $this->assertSame(1, PlatformPaymentConfirmation::count());
        $this->assertSame(1, PlatformEntitlementEvent::count());
        $this->assertSame(1, DB::table('entitlement_outbox')->count());
    }

    public function test_an_unknown_company_platform_or_plan_is_refused_alike_and_logged(): void
    {
        Log::spy();
        $personal = $this->team->owner->ownedTeams()->where('personal_team', true)->first();
        $draft = app(CatalogueAdmin::class)->createPlan('forms', ['name' => 'Draft', 'model' => 'subscription', 'amount_minor' => 5000, 'currency' => 'ZAR', 'active' => true], $this->administrator(), self::WHY);

        $wrong = [
            'unknown company' => ['company_uuid' => (string) Str::uuid()],
            'personal workspace' => ['company_uuid' => (string) $personal->uuid],
            'unknown platform' => ['platform' => 'nonsense'],
            'core platform' => ['platform' => 'files'],
            'platform with its own sign-in' => ['platform' => 'charts'],
            'unknown plan' => ['plan' => 'gold'],
            'plan of another platform' => ['platform' => 'projects'],
            'plan whose price nobody approved' => ['platform' => 'forms', 'plan' => $draft->slug],
            'unknown bundle' => ['bundle' => 'everything'],
        ];

        $answers = [];

        foreach ($wrong as $what => $change) {
            $response = $this->deliver($this->paymentEvent($this->team, 'paid', $change), $this->key);
            $response->assertStatus(422);
            $answers[] = $response->getContent();
        }

        // One answer for all of them: nothing says which part was wrong.
        $this->assertSame(['{"error":"unprocessable"}'], array_values(array_unique($answers)));
        Log::shouldHaveReceived('warning')->times(count($wrong));

        $this->assertSame(0, PlatformEntitlement::count());
        $this->assertSame(0, PlatformPaymentConfirmation::count(), 'a refused event is not stored, so it can be sent again once put right');
    }

    public function test_a_body_that_is_not_an_event_is_a_bad_request(): void
    {
        $good = $this->paymentEvent($this->team);

        $bad = [
            'not json' => 'paid=1',
            'a list' => '[1,2,3]',
            'unknown event' => ['event' => 'gift'] + $good,
            'no event id' => array_diff_key($good, ['event_id' => 1]),
            'event id with odd characters' => ['event_id' => "evt 1'--"] + $good,
            'no reference' => array_diff_key($good, ['reference' => 1]),
            'no amount on a payment' => array_diff_key($good, ['amount_minor' => 1]),
            'zero amount on a payment' => ['amount_minor' => 0] + $good,
            'amount as text' => ['amount_minor' => '24900'] + $good,
            'no currency' => array_diff_key($good, ['currency' => 1]),
            'lowercase currency' => ['currency' => 'zar'] + $good,
            'no period end on a payment' => array_diff_key($good, ['period_end' => 1]),
            'period end in the past' => ['period_end' => now()->subDay()->toIso8601String()] + $good,
            'no occurred_at' => array_diff_key($good, ['occurred_at' => 1]),
            'occurred_at that is not a date' => ['occurred_at' => 'yesterday'] + $good,
        ];

        foreach ($bad as $what => $body) {
            $this->deliver($body, $this->key)->assertStatus(400)->assertExactJson(['error' => 'bad_request']);
        }

        // A payment that names neither a plan nor a bundle is well formed but cannot be applied.
        $this->deliver(array_diff_key($good, ['plan' => 1]), $this->key)->assertStatus(422);

        $this->assertSame(0, PlatformEntitlement::count());
    }

    public function test_a_renewal_extends_the_period_and_survives_a_later_price_change(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();

        // The price changes: the plan is off sale until approved again. The company's renewal still goes through.
        app(CatalogueAdmin::class)->updatePlan($this->plan, ['amount_minor' => 34900], $this->administrator(), self::WHY);
        $this->assertFalse($this->plan->fresh()->isSellable());

        $this->travel(29)->days();
        $until = now()->addMonth();
        $this->deliver($this->paymentEvent($this->team, 'renewed', ['period_end' => $until->toIso8601String()]), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);

        $entitlement = $this->entitlement();
        $this->assertSame(['active', 'paid'], [$entitlement->status, $entitlement->source]);
        $this->assertEqualsWithDelta($until->timestamp, $entitlement->current_period_ends_at->timestamp, 5);
        $this->assertSame(['payment_confirmed', 'renewal_confirmed'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());

        // But a first payment for that unapproved price, from another company, is refused.
        $this->deliver($this->paymentEvent($this->company('Second Yard')), $this->key)->assertStatus(422);
        $this->deliver($this->paymentEvent($this->company('Third Yard'), 'renewed'), $this->key)->assertStatus(422);
    }

    public function test_a_failed_payment_gives_grace_and_then_expires(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();

        $this->deliver($this->paymentEvent($this->team, 'payment_failed'), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);
        $entitlement = $this->entitlement();
        $this->assertSame('payment_required', $entitlement->status);
        $this->assertEqualsWithDelta(now()->addDays((int) config('platform_subscriptions.grace_days'))->timestamp, $entitlement->grace_until->timestamp, 5);
        $this->assertTrue($this->access()->decideFor($this->team->owner, $this->team, 'hr')->allowed, 'in grace the company keeps the platform');
        $this->assertSame('payment_required', $this->access()->businessState($this->team, 'hr'));

        // A second failure does not push the date out.
        $grace = $entitlement->grace_until;
        $this->travel(1)->days();
        $this->deliver($this->paymentEvent($this->team, 'payment_failed'), $this->key)->assertOk()->assertExactJson(['status' => 'ignored']);
        $this->assertEquals($grace, $this->entitlement()->grace_until);

        $this->travel((int) config('platform_subscriptions.grace_days'))->days();
        $this->assertSame('payment_required', $this->access()->decideFor($this->team->owner, $this->team, 'hr')->reason);
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('1 grace period(s)')->assertSuccessful();
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('0 grace period(s)')->assertSuccessful();

        $this->assertSame('expired', $this->entitlement()->status);
        $this->assertSame(['payment_confirmed', 'payment_failed', 'grace_ended'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());
        $this->assertSame('subscription_expired', $this->access()->businessState($this->team, 'hr'));
    }

    public function test_dot_finance_may_name_a_shorter_grace_date_but_not_an_endless_one(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->deliver($this->paymentEvent($this->team, 'payment_failed', ['grace_until' => now()->addYears(3)->toIso8601String()]), $this->key)->assertOk();

        $this->assertEqualsWithDelta(now()->addDays((int) config('platform_subscriptions.max_grace_days'))->timestamp, $this->entitlement()->grace_until->timestamp, 5);
    }

    public function test_a_payment_after_a_failure_makes_it_active_again(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->deliver($this->paymentEvent($this->team, 'payment_failed'), $this->key)->assertOk();
        $this->travel(1)->days();
        $this->deliver($this->paymentEvent($this->team, 'renewed'), $this->key)->assertOk();

        $this->assertSame(['active', null], [$this->entitlement()->status, $this->entitlement()->grace_until]);
    }

    public function test_a_cancellation_keeps_access_to_the_end_of_the_paid_period(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->deliver($this->paymentEvent($this->team, 'cancelled'), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);

        $entitlement = $this->entitlement();
        $this->assertSame(['cancelled', null], [$entitlement->status, $entitlement->ended_at]);
        $this->assertTrue($this->access()->decideFor($this->team->owner, $this->team, 'hr')->allowed);
        $this->assertSame('active', $this->access()->businessState($this->team, 'hr'));

        $this->travel(32)->days();
        $this->assertSame('cancelled', $this->access()->decideFor($this->team->owner, $this->team, 'hr')->reason);
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('1 paid period(s)')->assertSuccessful();
        $this->artisan('platforms:lifecycle')->expectsOutputToContain('0 paid period(s)')->assertSuccessful();

        $this->assertNotNull($this->entitlement()->ended_at);
        $this->assertSame(['payment_confirmed', 'cancelled', 'period_ended'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_the_owner_cancelling_a_paid_platform_also_keeps_the_paid_time(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();

        $cancelled = $this->entitlements()->cancel($this->team, 'hr', $this->team->owner);

        $this->assertSame(['cancelled', null], [$cancelled->status, $cancelled->ended_at]);
        $this->assertTrue($cancelled->grants());
    }

    public function test_a_refund_ends_access_at_once(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->deliver($this->paymentEvent($this->team, 'refunded'), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);

        $this->assertSame('cancelled', $this->entitlement()->status);
        $this->assertNotNull($this->entitlement()->ended_at);
        $this->assertSame('cancelled', $this->access()->decideFor($this->team->owner, $this->team, 'hr')->reason);
        $this->assertSame(['payment_confirmed', 'refunded'], PlatformEntitlementEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_failure_refund_and_cancellation_mean_nothing_without_a_paid_entitlement(): void
    {
        $this->activatePlatforms($this->team, 'forms');

        foreach (['payment_failed', 'refunded', 'cancelled'] as $event) {
            $this->deliver($this->paymentEvent($this->team, $event), $this->key)->assertStatus(422);
            $this->deliver($this->paymentEvent($this->team, $event, ['platform' => 'forms']), $this->key)->assertStatus(422);
        }

        $this->assertNull($this->entitlement());
        $this->assertSame(['active', 'unpriced_free'], [$this->entitlement('forms')->status, $this->entitlement('forms')->source]);
        $this->assertSame(0, PlatformPaymentConfirmation::count());
    }

    public function test_an_event_older_than_one_already_applied_is_stored_and_ignored(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->travel(1)->hours();
        $this->deliver($this->paymentEvent($this->team, 'renewed'), $this->key)->assertOk();

        // A failure that happened before the renewal arrives late.
        $late = $this->paymentEvent($this->team, 'payment_failed', ['occurred_at' => now()->subMinutes(30)->toIso8601String()]);
        $this->deliver($late, $this->key)->assertOk()->assertExactJson(['status' => 'ignored']);
        $this->deliver($late, $this->key)->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame('active', $this->entitlement()->status);
        $this->assertSame(['applied', 'applied', 'ignored'], PlatformPaymentConfirmation::orderBy('id')->pluck('outcome')->all());
    }

    public function test_a_payment_does_not_lift_a_suspension(): void
    {
        $admin = $this->administrator();
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->entitlements()->suspend($this->team, 'hr', $admin, self::WHY);

        $this->deliver($this->paymentEvent($this->team, 'renewed', ['period_end' => now()->addMonths(2)->toIso8601String()]), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);

        $this->assertSame('suspended', $this->entitlement()->status);
        $this->assertSame('suspended', $this->access()->decideFor($this->team->owner, $this->team, 'hr')->reason);
        $this->assertEqualsWithDelta(now()->addMonths(2)->timestamp, $this->entitlement()->current_period_ends_at->timestamp, 5);

        $this->assertSame('active', $this->entitlements()->restore($this->team, 'hr', $admin, self::WHY)->status);
    }

    public function test_a_plan_change_arrives_with_the_payment_and_the_history_keeps_both(): void
    {
        $bigger = $this->plan('hr', ['name' => 'Company', 'amount_minor' => 49900]);
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();
        $this->deliver($this->paymentEvent($this->team, 'renewed', ['plan' => $bigger->slug, 'amount_minor' => 49900]), $this->key)->assertOk();

        $this->assertSame($bigger->id, $this->entitlement()->plan_id);
        $this->assertSame('team', PlatformEntitlementEvent::where('type', 'renewal_confirmed')->sole()->details['plan_changed_from']);
    }

    public function test_a_bundle_payment_is_applied_platform_by_platform(): void
    {
        $bundle = $this->bundle(['hr', 'projects']);

        foreach (['hr', 'projects'] as $platform) {
            $this->deliver($this->paymentEvent($this->team, 'paid', ['platform' => $platform, 'plan' => null, 'bundle' => $bundle->slug, 'amount_minor' => 39900]), $this->key)->assertOk()->assertExactJson(['status' => 'applied']);
        }

        $this->assertSame([$bundle->id, $bundle->id], [$this->entitlement('hr')->bundle_id, $this->entitlement('projects')->bundle_id]);
        $this->assertSame(['active', 'paid'], [$this->entitlement('projects')->status, $this->entitlement('projects')->source]);

        // A platform the bundle does not hold is not opened by it.
        $this->deliver($this->paymentEvent($this->team, 'paid', ['platform' => 'forms', 'plan' => null, 'bundle' => $bundle->slug]), $this->key)->assertStatus(422);
        $this->assertNull($this->entitlement('forms'));
    }

    public function test_the_endpoint_is_throttled_like_the_other_finance_callback(): void
    {
        $route = app('router')->getRoutes()->getByName('api.finance.platform-subscription-events');

        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('throttle:finance-callbacks', $route->gatherMiddleware());
    }

    public function test_nothing_else_in_the_application_can_mark_anything_paid(): void
    {
        // The payment is applied in one method, which takes only a stored confirmation ...
        $loose = new PlatformPaymentConfirmation(['event_id' => 'made-up', 'event' => 'paid', 'team_id' => $this->team->id, 'platform_key' => 'hr', 'outcome' => 'applied']);

        try {
            $this->entitlements()->applyPaymentEvent($loose, $this->team, $this->plan);
            $this->fail('A payment was applied without a stored confirmation.');
        } catch (LogicException) {
            $this->assertNull($this->entitlement());
        }

        // ... is called from one place, which is called from one controller ...
        $appliers = [];
        $callers = [];
        $paidWriters = [];

        foreach ((new Finder)->files()->in([app_path(), base_path('routes'), base_path('database'), base_path('config')])->name('*.php') as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            $code = $file->getContents();

            if (str_contains($code, 'applyPaymentEvent(') && $path !== 'app/Services/Platforms/Entitlements.php') {
                $appliers[] = $path;
            }

            if (preg_match('/\bPaymentConfirmations\b/', (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code)) && $path !== 'app/Services/Platforms/PaymentConfirmations.php') {
                $callers[] = $path;
            }

            if (preg_match('/[\'"]source[\'"]\s*=>\s*[\'"]paid[\'"]/', $code)) {
                $paidWriters[] = $path;
            }
        }

        $this->assertSame(['app/Services/Platforms/PaymentConfirmations.php'], $appliers);
        $this->assertSame(['app/Http/Controllers/Api/PlatformSubscriptionEventsController.php'], $callers);
        $this->assertSame(['app/Services/Platforms/Entitlements.php'], $paidWriters);

        // ... and there is no switch that turns the signature check off.
        $this->assertSame(['confirmation_secret', 'grace_days', 'max_grace_days', 'max_trial_days', 'signature_window'], collect(config('platform_subscriptions'))->keys()->sort()->values()->all());

        foreach (['app/Http/Controllers/Api/PlatformSubscriptionEventsController.php', 'app/Services/Platforms/PaymentConfirmations.php'] as $path) {
            $this->assertDoesNotMatchRegularExpression('/environment\(|app\.env|APP_ENV|app\.debug|runningUnitTests|simulat|sandbox|fake/i', preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents(base_path($path))), $path);
        }

        // No admin action leads there either: with every admin power used, the company still holds nothing paid.
        $admin = $this->administrator();
        $this->entitlements()->grantComplimentary($this->team, 'hr', $admin, null, self::WHY);
        $this->entitlements()->startTrial($this->team, 'projects', $admin, null, 5, self::WHY);
        $this->assertSame(0, PlatformEntitlement::where('source', 'paid')->count());
    }

    public function test_a_confirmation_cannot_be_deleted(): void
    {
        $this->deliver($this->paymentEvent($this->team), $this->key)->assertOk();

        $this->expectException(LogicException::class);
        PlatformPaymentConfirmation::sole()->delete();
    }
}
