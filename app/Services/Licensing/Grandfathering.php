<?php

namespace App\Services\Licensing;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamLicence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The transition for companies that existed before the one-free-licence rule (R-53, owner decision D7: freeze and sunset).
 *
 * Every company with more people than it would get free is frozen at its current headcount (the owner, the members and the invitations that
 * are still live), for `licensing.grandfather_days` days. It keeps everyone and can add nobody above that headcount without a paid licence.
 * Nobody is removed, no account or content is touched, and everything is reversible: the row's previous values are stored with it and
 * `rollback()` puts them back.
 *
 * `plan()` never writes. `apply()` and `rollback()` are idempotent: a company already frozen is skipped, a company not frozen is skipped.
 */
class Grandfathering
{
    public function __construct(private SeatLedger $ledger) {}

    /**
     * What `apply()` would do, per company.
     *
     * @param  array<int>  $teamIds  empty = every company
     * @return list<array{team_id: int, name: string, headcount: int, purchased: int, current_included: ?int, new_included: ?int, action: string}>
     */
    public function plan(array $teamIds = []): array
    {
        $rows = [];

        foreach ($this->companies($teamIds) as $team) {
            $licence = TeamLicence::where('team_id', $team->id)->first();   // read only: a dry run never creates a row
            $rows[] = $this->describe($team, $licence);
        }

        return $rows;
    }

    /**
     * Freezes every company that needs it. Returns the plan rows that were applied, with their action.
     *
     * @param  array<int>  $teamIds
     * @return list<array<string, mixed>>
     */
    public function apply(array $teamIds = [], ?int $days = null): array
    {
        $days ??= (int) config('licensing.grandfather_days', 90);
        $out = [];

        foreach ($this->companies($teamIds) as $team) {
            $out[] = DB::transaction(function () use ($team, $days) {
                $locked = Team::whereKey($team->id)->lockForUpdate()->first();
                $licence = $this->ledger->licenceFor($locked);
                $row = $this->describe($locked, $licence);

                if ($row['action'] !== 'freeze') {
                    return $row;
                }

                $until = now()->addDays(max(1, $days));
                $licence->forceFill([
                    'grandfather_snapshot' => ['included_seats' => $licence->included_seats, 'grandfathered_until' => $licence->grandfathered_until?->toIso8601String(), 'headcount' => $row['headcount'], 'applied_at' => now()->toIso8601String()],
                    'included_seats' => $row['new_included'],
                    'grandfathered_until' => $until,
                ])->save();

                $this->ledger->record($locked, null, 'grandfathered', null, [
                    'headcount' => $row['headcount'], 'included_before' => $row['current_included'], 'included_after' => $row['new_included'], 'until' => $until->toIso8601String(), 'days' => $days,
                ]);

                return $row + ['until' => $until];
            });
        }

        return $out;
    }

    /**
     * What `rollback()` would restore.
     *
     * @param  array<int>  $teamIds
     * @return list<array<string, mixed>>
     */
    public function rollbackPlan(array $teamIds = []): array
    {
        return TeamLicence::query()
            ->whereNotNull('grandfather_snapshot')
            ->when($teamIds !== [], fn ($q) => $q->whereIn('team_id', $teamIds))
            ->orderBy('team_id')->get()
            ->map(fn (TeamLicence $l) => ['team_id' => $l->team_id, 'current_included' => $l->included_seats, 'restore_included' => $l->grandfather_snapshot['included_seats'] ?? null, 'action' => 'restore'])
            ->all();
    }

    /**
     * Puts each frozen company's row back as the snapshot recorded it. Idempotent: the snapshot is cleared, so a second run finds nothing.
     *
     * @param  array<int>  $teamIds
     * @return list<array<string, mixed>>
     */
    public function rollback(array $teamIds = []): array
    {
        $out = [];

        foreach ($this->rollbackPlan($teamIds) as $row) {
            DB::transaction(function () use ($row, &$out) {
                $team = Team::whereKey($row['team_id'])->lockForUpdate()->first();
                $licence = $team ? TeamLicence::where('team_id', $team->id)->lockForUpdate()->first() : null;

                if (! $licence || $licence->grandfather_snapshot === null) {
                    return;
                }

                $snap = $licence->grandfather_snapshot;
                $licence->forceFill([
                    'included_seats' => $snap['included_seats'] ?? null,
                    'grandfathered_until' => isset($snap['grandfathered_until']) ? Carbon::parse($snap['grandfathered_until']) : null,
                    'grandfather_snapshot' => null,
                ])->save();

                $this->ledger->record($team, null, 'grandfather_rolled_back', null, ['included_restored' => $snap['included_seats'] ?? null]);
                $out[] = $row;
            });
        }

        return $out;
    }

    /** @return array{team_id: int, name: string, headcount: int, purchased: int, current_included: ?int, new_included: ?int, action: string} */
    private function describe(Team $team, ?TeamLicence $licence): array
    {
        $purchased = $licence === null ? 0 : $licence->purchased_seats;
        $headcount = $team->users()->count() + 1   // the owner
            + (config('licensing.pending_invitations_reserve_a_seat', true) ? TeamInvitation::live()->where('team_id', $team->id)->count() : 0);
        $base = max(0, (int) config('licensing.target_included_seats'));
        // Capacity after freezing is exactly the headcount: whatever has been bought stays on top of the frozen free part.
        $needed = $headcount - $purchased;

        $action = match (true) {
            $licence?->grandfather_snapshot !== null => 'already frozen',
            $licence?->included_seats !== null => 'manual override kept',   // set by hand on purpose; this command never overwrites it
            $needed <= $base => 'within the free allowance',
            default => 'freeze',
        };

        return [
            'team_id' => $team->id, 'name' => (string) $team->name, 'headcount' => $headcount, 'purchased' => $purchased,
            'current_included' => $licence?->included_seats, 'new_included' => $action === 'freeze' ? $needed : $licence?->included_seats, 'action' => $action,
        ];
    }

    /** @return iterable<Team> */
    private function companies(array $teamIds): iterable
    {
        return Team::query()->where('personal_team', false)
            ->when($teamIds !== [], fn ($q) => $q->whereIn('id', $teamIds))
            ->orderBy('id')->lazyById(200);
    }
}
