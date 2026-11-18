<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\PlatformPaymentConfirmation;
use App\Models\Team;
use App\Services\Demo\DemoRegistry;
use App\Services\Platforms\PaymentEventRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a licence confirmation that Dot.Finance signed (`kind: licence_batch` on POST /api/finance/platform-subscription-events), after
 * the controller has verified the signature and the shape. This is the only thing that ever makes a licence batch active: no redirect, no
 * browser value and no test switch can.
 *
 * Idempotent by `event_id`: the confirmation row is inserted first (unique), in the same transaction as the change, so a repeated delivery
 * answers `duplicate` and changes nothing. Events can arrive late or in the wrong order, so each batch moves only along allowed steps and
 * an event the batch has already moved past is stored and answered `ignored`:
 *
 *   paid          pending -> active (period set, capacity up). Anything else: ignored.
 *   renewed       active or grace -> active, period extended; a smaller quantity is a reduction and starts the 14-day grace for the leftovers.
 *   payment_failed active -> grace (14 days from the period end), banner and email follow in the screens.
 *   cancelled     active or grace -> ends at the period end; a pending batch just closes.
 *   refunded      active or grace -> grace (14 days), then its people lapse; a pending batch is closed.
 *
 * A batch that was never paid cannot be renewed into existence, and an ended batch cannot be revived by a late event.
 */
class LicenceBatchEvents
{
    public const LEDGER_KEY = 'licences';

    public const APPLIED = 'applied';

    public const DUPLICATE = 'duplicate';

    public const IGNORED = 'ignored';

    public function __construct(private SeatLedger $ledger, private LicenceLifecycle $lifecycle) {}

    /**
     * @param  array<string, mixed>  $body  the decoded event, shape-checked by the controller
     *
     * @throws PaymentEventRefused when it names something unknown or does not match what was sold; nothing is stored
     */
    public function apply(array $body): string
    {
        $eventId = (string) $body['event_id'];
        $type = (string) $body['type'];

        if (PlatformPaymentConfirmation::where('event_id', $eventId)->exists()) {
            return self::DUPLICATE;
        }

        $team = Team::where('uuid', (string) $body['team_uuid'])->where('personal_team', false)->first();

        if ($team === null || app(DemoRegistry::class)->isDemoTeam($team)) {
            throw new PaymentEventRefused('unknown company');
        }

        $batch = LicenceBatch::where('uuid', (string) $body['batch_uuid'])->first();

        if ($batch === null || (int) $batch->team_id !== (int) $team->id) {
            throw new PaymentEventRefused('unknown batch, or a batch of another company');
        }

        $occurred = isset($body['occurred_at']) ? CarbonImmutable::parse((string) $body['occurred_at']) : CarbonImmutable::now();
        $periodStart = isset($body['period_start']) ? CarbonImmutable::parse((string) $body['period_start']) : null;
        $periodEnd = isset($body['period_end']) ? CarbonImmutable::parse((string) $body['period_end']) : null;

        return DB::transaction(function () use ($body, $eventId, $type, $team, $batch, $occurred, $periodStart, $periodEnd) {
            // Events about one company are applied one at a time.
            Team::whereKey($team->id)->lockForUpdate()->first();
            $batch = LicenceBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            $this->mustMatchWhatWasSold($type, $body, $batch);

            $stale = $batch->last_event_at !== null && $occurred->lt($batch->last_event_at);
            $change = $stale ? null : $this->change($type, $body, $batch, $periodStart, $periodEnd);

            $inserted = DB::table('platform_payment_confirmations')->insertOrIgnore([
                'event_id' => $eventId, 'event' => $type, 'team_id' => $team->id, 'platform_key' => self::LEDGER_KEY,
                'plan_id' => null, 'bundle_id' => null, 'amount_minor' => $body['amount_minor'] ?? null, 'currency' => $body['currency'] ?? null,
                'reference' => (string) $body['paystack_reference'], 'period_ends_at' => $periodEnd, 'occurred_at' => $occurred,
                'outcome' => $change === null ? PlatformPaymentConfirmation::IGNORED : PlatformPaymentConfirmation::APPLIED, 'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return self::DUPLICATE;   // lost a race with the same event; nothing below ran against the batch
            }

            if ($change === null) {
                return self::IGNORED;
            }

            $batch->forceFill($change['set'] + ['last_event_at' => $occurred, 'payment_ref' => mb_substr((string) $body['paystack_reference'], 0, 120)])->save();
            $this->ledger->record($team, null, $change['audit'], $batch->uuid, $change['details'] + ['event_id' => $eventId, 'quantity' => $batch->quantity]);
            $this->lifecycle->rebalance($team->refresh(), $change['immediate'] ?? false);

            return self::APPLIED;
        });
    }

    /**
     * A signed event must still describe the purchase InfoDot priced: the price and quantity are InfoDot's, never Finance's or a browser's.
     *
     * @param  array<string, mixed>  $body
     */
    private function mustMatchWhatWasSold(string $type, array $body, LicenceBatch $batch): void
    {
        if (isset($body['currency']) && $body['currency'] !== $batch->currency) {
            throw new PaymentEventRefused('currency does not match the purchase');
        }

        if ($type === PlatformPaymentConfirmation::PAID) {
            if ((int) $body['quantity'] !== $batch->quantity || (string) $body['interval'] !== $batch->interval || (int) $body['amount_minor'] !== $batch->total_minor) {
                throw new PaymentEventRefused('the payment does not match the quantity, interval and total InfoDot priced');
            }
        } elseif ((string) $body['interval'] !== $batch->interval) {
            throw new PaymentEventRefused('interval does not match the purchase');
        }

        if ($type === PlatformPaymentConfirmation::RENEWED && (int) $body['quantity'] > $batch->quantity) {
            throw new PaymentEventRefused('a renewal cannot add licences: a new purchase is a new batch');
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{set: array<string, mixed>, audit: string, details: array<string, mixed>, immediate?: bool}|null null = nothing to do (ignored)
     */
    private function change(string $type, array $body, LicenceBatch $batch, ?CarbonImmutable $periodStart, ?CarbonImmutable $periodEnd): ?array
    {
        $status = $batch->status;
        $graceDays = max(1, (int) config('licensing.grace_days', 14));

        switch ($type) {
            case PlatformPaymentConfirmation::PAID:
                if ($status !== LicenceBatch::PENDING) {
                    return null;
                }

                return ['set' => ['status' => LicenceBatch::ACTIVE, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'grace_until' => null], 'audit' => 'batch_activated', 'details' => ['period_end' => $periodEnd?->toIso8601String(), 'amount_minor' => $body['amount_minor']]];

            case PlatformPaymentConfirmation::RENEWED:
                if (! in_array($status, [LicenceBatch::ACTIVE, LicenceBatch::GRACE], true) || $periodEnd === null || ($batch->period_end !== null && ! $periodEnd->gt($batch->period_end))) {
                    return null;   // a never-paid batch is not renewed into existence; an older renewal changes nothing
                }

                $set = ['status' => LicenceBatch::ACTIVE, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'grace_until' => null, 'quantity_next' => null];
                $quantity = (int) $body['quantity'];

                if ($quantity < $batch->quantity) {
                    $set += ['quantity' => $quantity, 'total_minor' => (int) $body['amount_minor']];
                } elseif ((int) $body['amount_minor'] !== $batch->total_minor) {
                    Log::warning('A licence renewal was paid at a different amount than the purchase was priced at; the licences are kept.', ['batch' => $batch->uuid, 'expected' => $batch->total_minor, 'got' => $body['amount_minor']]);
                }

                return ['set' => $set, 'audit' => 'batch_renewed', 'details' => ['period_end' => $periodEnd->toIso8601String(), 'reduced_to' => $quantity < $batch->quantity ? $quantity : null]];

            case PlatformPaymentConfirmation::PAYMENT_FAILED:
                if ($status !== LicenceBatch::ACTIVE) {
                    return null;
                }

                $until = ($batch->period_end !== null && $batch->period_end->isFuture() ? $batch->period_end : now())->copy()->addDays($graceDays);

                return ['set' => ['status' => LicenceBatch::GRACE, 'grace_until' => $until], 'audit' => 'batch_payment_failed', 'details' => ['grace_until' => $until->toIso8601String()]];

            case PlatformPaymentConfirmation::CANCELLED:
                if ($status === LicenceBatch::PENDING) {
                    return ['set' => ['status' => LicenceBatch::ENDED], 'audit' => 'batch_ended', 'details' => ['reason' => 'cancelled_before_payment']];
                }

                if (! in_array($status, [LicenceBatch::ACTIVE, LicenceBatch::GRACE], true) || $batch->cancelled_at !== null) {
                    return null;
                }

                return ['set' => ['cancelled_at' => now(), 'quantity_next' => null], 'audit' => 'batch_cancelled', 'details' => ['ends' => $batch->period_end?->toIso8601String()]];

            case PlatformPaymentConfirmation::REFUNDED:
                if ($status === LicenceBatch::PENDING) {
                    return ['set' => ['status' => LicenceBatch::REFUNDED, 'grace_until' => null], 'audit' => 'batch_refunded', 'details' => ['before_activation' => true]];
                }

                if (! in_array($status, [LicenceBatch::ACTIVE, LicenceBatch::GRACE], true)) {
                    return null;
                }

                $until = now()->addDays($graceDays);

                return ['set' => ['status' => LicenceBatch::REFUNDED, 'grace_until' => $until], 'audit' => 'batch_refunded', 'details' => ['grace_until' => $until->toIso8601String()]];
        }

        return null;
    }
}
