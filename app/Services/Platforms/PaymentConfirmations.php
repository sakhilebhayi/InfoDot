<?php

namespace App\Services\Platforms;

use App\Models\PlatformBundle;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPaymentConfirmation;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Services\Demo\DemoRegistry;
use App\Support\EcosystemHandoff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Takes a payment event whose signature the controller has already verified, stores it once and applies it (R-50
 * section 6). This is the only caller of Entitlements::applyPaymentEvent(), and so the only way anything in this
 * application becomes paid. It does not talk to a payment provider and has no test or simulation mode: it believes
 * Dot.Finance's signature and nothing else.
 *
 * Applied once per `event_id`: the confirmation row is inserted first, inside the same transaction as the change, and
 * a second delivery of the same id finds it and answers `duplicate`. An event older than one already applied for the
 * same company and platform is stored and answered `ignored`, so a late `payment_failed` cannot undo a renewal.
 */
class PaymentConfirmations
{
    public const APPLIED = 'applied';

    public const DUPLICATE = 'duplicate';

    public const IGNORED = 'ignored';

    public function __construct(private readonly Entitlements $entitlements, private readonly PlatformAccess $access) {}

    /**
     * @param  array<string, mixed>  $body  the decoded event, already shape-checked by the controller
     *
     * @throws PaymentEventRefused when the event names something unknown or cannot apply; nothing is stored
     */
    public function apply(array $body): string
    {
        $event = (string) $body['event'];
        $key = (string) $body['platform'];
        $confirms = in_array($event, [PlatformPaymentConfirmation::PAID, PlatformPaymentConfirmation::RENEWED], true);

        if (PlatformPaymentConfirmation::where('event_id', $body['event_id'])->exists()) {
            return self::DUPLICATE;
        }

        $team = Team::where('uuid', (string) $body['company_uuid'])->where('personal_team', false)->first();

        if ($team === null || app(DemoRegistry::class)->isDemoTeam($team)) {
            throw new PaymentEventRefused('unknown company');
        }

        $entry = $this->access->governs($key) ? $this->access->entry($key) : null;

        if ($entry === null || $entry->is_core || EcosystemHandoff::hasNoReceiver($key)) {
            throw new PaymentEventRefused('unknown platform, or one that is not sold');
        }

        $current = $this->access->entitlement($team, $key);
        $plan = isset($body['plan']) ? PlatformPlan::where('platform_key', $key)->where('slug', (string) $body['plan'])->first() : null;
        $bundle = isset($body['bundle']) ? PlatformBundle::where('slug', (string) $body['bundle'])->first() : null;

        if ((isset($body['plan']) && $plan === null) || (isset($body['bundle']) && $bundle === null)) {
            throw new PaymentEventRefused('unknown plan or bundle');
        }

        if ($confirms) {
            $this->mustBeWhatWasSold($event, $key, $plan, $bundle, $current);
        }

        $occurred = CarbonImmutable::parse((string) $body['occurred_at']);
        $periodEnd = isset($body['period_end']) ? CarbonImmutable::parse((string) $body['period_end']) : null;
        $grace = isset($body['grace_until']) ? CarbonImmutable::parse((string) $body['grace_until'])->min(now()->addDays((int) config('platform_subscriptions.max_grace_days'))) : null;

        try {
            return DB::transaction(function () use ($body, $event, $key, $team, $plan, $bundle, $occurred, $periodEnd, $grace) {
                // Events about one company are applied one at a time.
                Team::whereKey($team->id)->lockForUpdate()->first();

                $newer = PlatformPaymentConfirmation::where('team_id', $team->id)->where('platform_key', $key)
                    ->where('outcome', PlatformPaymentConfirmation::APPLIED)->where('occurred_at', '>', $occurred)->exists();

                $inserted = DB::table('platform_payment_confirmations')->insertOrIgnore([
                    'event_id' => (string) $body['event_id'], 'event' => $event, 'team_id' => $team->id, 'platform_key' => $key,
                    'plan_id' => $plan?->id, 'bundle_id' => $bundle?->id, 'amount_minor' => $body['amount_minor'] ?? null,
                    'currency' => $body['currency'] ?? null, 'reference' => (string) $body['reference'], 'period_ends_at' => $periodEnd,
                    'occurred_at' => $occurred, 'outcome' => $newer ? PlatformPaymentConfirmation::IGNORED : PlatformPaymentConfirmation::APPLIED, 'created_at' => now(),
                ]);

                if ($inserted === 0) {
                    return self::DUPLICATE;
                }

                if ($newer) {
                    return self::IGNORED;
                }

                $confirmation = PlatformPaymentConfirmation::where('event_id', (string) $body['event_id'])->firstOrFail();

                if (! $this->entitlements->applyPaymentEvent($confirmation, $team, $plan, $bundle, $grace)) {
                    PlatformPaymentConfirmation::whereKey($confirmation->id)->update(['outcome' => PlatformPaymentConfirmation::IGNORED]);

                    return self::IGNORED;
                }

                return self::APPLIED;
            });
        } catch (EntitlementRefused $e) {
            // The transaction rolled back: the event is not stored and may be sent again once the cause is put right.
            throw new PaymentEventRefused($e->getMessage(), 0, $e);
        }
    }

    /** A payment must be for something on sale; a renewal may also be for the plan the company already holds, at whatever price it had. */
    private function mustBeWhatWasSold(string $event, string $key, ?PlatformPlan $plan, ?PlatformBundle $bundle, ?PlatformEntitlement $current): void
    {
        if ($plan === null && $bundle === null) {
            throw new PaymentEventRefused('a payment names its plan or bundle');
        }

        $renewal = $event === PlatformPaymentConfirmation::RENEWED && $current !== null && $current->source === 'paid';

        if ($plan !== null && ! (($plan->isSellable() && ! $plan->isFree()) || ($renewal && (int) $current->plan_id === (int) $plan->id))) {
            throw new PaymentEventRefused('the plan is not on sale');
        }

        if ($bundle !== null) {
            $onSale = $bundle->active && $bundle->approved_at !== null && $bundle->amount_minor !== null && $bundle->currency !== null;

            if (! $bundle->items()->where('platform_key', $key)->exists() || ! ($onSale || ($renewal && (int) $current->bundle_id === (int) $bundle->id))) {
                throw new PaymentEventRefused('the bundle is not on sale or does not hold this platform');
            }
        }
    }
}
