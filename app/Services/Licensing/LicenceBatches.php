<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The owner's actions on paid licence purchases (R-53). Services only: the screens come later. Every one is owner-only, priced on the
 * server from configuration, written to the audit trail, and never gives anyone a licence: a batch becomes active only through
 * LicenceBatchEvents, on a verified `paid` confirmation from Dot.Finance.
 */
class LicenceBatches
{
    /** A company with this many unpaid purchases open cannot start another until some are paid or closed. */
    private const MAX_PENDING = 5;

    public function __construct(private SeatLedger $ledger, private LicencePricing $pricing) {}

    /**
     * Starts a purchase: a pending batch priced from configuration. Nothing is charged and nothing is active. The caller sends the batch
     * to Dot.Finance's checkout (POST /api/licences/checkout) and waits for the signed `paid` confirmation.
     *
     * @throws LicenceRefused
     */
    public function createPending(Team $team, User $owner, int $quantity, string $interval): LicenceBatch
    {
        Gate::forUser($owner)->authorize('changeLicence', $team);

        if ($team->personal_team) {
            throw new LicenceRefused('A personal workspace is one person and has nothing to buy.');
        }

        return DB::transaction(function () use ($team, $owner, $quantity, $interval) {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();

            if (LicenceBatch::where('team_id', $locked->getKey())->where('status', LicenceBatch::PENDING)->count() >= self::MAX_PENDING) {
                throw new LicenceRefused('There are already unpaid purchases waiting. Finish or let them close before starting another.');
            }

            $quote = $this->pricing->quote($locked, $quantity, $interval);

            $batch = LicenceBatch::create([
                'team_id' => $locked->getKey(), 'quantity' => $quantity, 'interval' => $interval,
                'unit_price_minor' => $quote['unit_price_minor'], 'total_minor' => $quote['total_minor'], 'currency' => $quote['currency'],
                'price_snapshot' => $quote['snapshot'], 'created_by' => $owner->getKey(),
            ]);
            $this->ledger->record($locked, $owner, 'batch_created', $batch->uuid, ['quantity' => $quantity, 'interval' => $interval, 'total_minor' => $quote['total_minor'], 'currency' => $quote['currency']]);

            return $batch;
        });
    }

    /**
     * Schedules fewer licences from the next renewal (D5): nothing changes today and nobody is removed. The reduction takes effect when the
     * subscription renews at the smaller quantity; until then the owner chooses who to release.
     *
     * @throws LicenceRefused
     */
    public function reduceAtPeriodEnd(LicenceBatch $batch, User $owner, int $newQuantity): LicenceBatch
    {
        $team = $batch->team;
        Gate::forUser($owner)->authorize('changeLicence', $team);

        return DB::transaction(function () use ($batch, $owner, $newQuantity, $team) {
            $locked = LicenceBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LicenceBatch::ACTIVE, LicenceBatch::GRACE], true)) {
                throw new LicenceRefused('Only licences that are paid for can be reduced.');
            }

            if ($newQuantity < 0 || $newQuantity >= $locked->quantity) {
                throw new LicenceRefused('Choose a smaller number of licences, or cancel the purchase to stop it renewing.');
            }

            if ($newQuantity === 0) {
                return $this->cancelAtPeriodEnd($locked, $owner);
            }

            $locked->forceFill(['quantity_next' => $newQuantity])->save();
            $this->ledger->record($team, $owner, 'batch_reduction_scheduled', $locked->uuid, ['from' => $locked->quantity, 'to' => $newQuantity, 'effective' => $locked->period_end?->toIso8601String()]);

            return $locked;
        });
    }

    /**
     * Stops a purchase renewing: it keeps running to the end of the period already paid, then ends and its people get the 14 days. Recorded
     * here as the owner's request; Dot.Finance's `cancelled` confirmation records the same fact from the provider's side.
     */
    public function cancelAtPeriodEnd(LicenceBatch $batch, User $owner): LicenceBatch
    {
        $team = $batch->team;
        Gate::forUser($owner)->authorize('changeLicence', $team);

        return DB::transaction(function () use ($batch, $owner, $team) {
            $locked = LicenceBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [LicenceBatch::ACTIVE, LicenceBatch::GRACE], true)) {
                throw new LicenceRefused('Only licences that are paid for can be cancelled.');
            }

            if ($locked->cancelled_at === null) {
                $locked->forceFill(['cancelled_at' => now(), 'quantity_next' => null])->save();
                $this->ledger->record($team, $owner, 'batch_cancel_requested', $locked->uuid, ['ends' => $locked->period_end?->toIso8601String()]);
            }

            return $locked;
        });
    }
}
