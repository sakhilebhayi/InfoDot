<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\TeamLicence;
use Illuminate\Support\Facades\DB;

/**
 * What happens when a company's paid licences change or run out (R-53, decisions D5 and D8). One rule, `rebalance()`, decides it:
 *
 *  - Room (capacity at least as big as the people holding licences): lapsed people get their licence back at once, longest-standing first,
 *    and any running grace is cleared.
 *  - Over capacity: nobody loses anything on the day. A 14-day grace starts (`team_licences.grace_until`). When it has passed and the company
 *    is still over, the people beyond capacity lapse, most recently added first. A batch that has already had its own grace (failed payment,
 *    refund) skips the second one: its people lapse straight away.
 *  - The owner and administrators never lapse. No data, account or membership is ever deleted.
 *
 * `sweep()` is the scheduled job: it moves batches along by their dates and rebalances the companies that need it. Dates are also checked
 * everywhere capacity is read, so a missed run cannot leave an expired batch counting.
 */
class LicenceLifecycle
{
    public function __construct(private SeatLedger $ledger, private LicenceAssignments $assignments) {}

    /**
     * @return array{lapsed: int, reactivated: int, grace_started: bool}
     */
    public function rebalance(Team $team, bool $immediate = false): array
    {
        return DB::transaction(function () use ($team, $immediate) {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();
            $result = ['lapsed' => 0, 'reactivated' => 0, 'grace_started' => false];

            if (! $this->ledger->applies($locked)) {
                return $result;
            }

            $licence = $this->ledger->licenceFor($locked);
            $capacity = $this->ledger->summary($locked)['capacity'];
            $licensed = $this->assignments->licensedCount($locked);

            if ($licensed <= $capacity) {
                $result['reactivated'] = $this->assignments->reactivateUpTo($locked, $capacity - $licensed);

                if ($licence->grace_until !== null) {
                    $licence->forceFill(['grace_until' => null])->save();
                    $this->ledger->record($locked, null, 'licence_grace_cleared', null, ['reason' => 'room']);
                }

                return $result;
            }

            $candidates = $this->assignments->lapseCandidates($locked)->count();

            if ($candidates === 0) {
                return $result;   // only the owner and administrators are over: they never lapse
            }

            $graceOver = $licence->grace_until !== null && ! $licence->grace_until->isFuture();

            if ($immediate || $graceOver) {
                $result['lapsed'] = $this->assignments->lapseSurplus($locked, $licensed - $capacity, $immediate ? 'payment_grace_ended' : 'grace_ended');

                if ($licence->grace_until !== null) {
                    $licence->forceFill(['grace_until' => null])->save();
                }

                return $result;
            }

            if ($licence->grace_until === null) {
                $until = now()->addDays(max(1, (int) config('licensing.grace_days', 14)));
                $licence->forceFill(['grace_until' => $until])->save();
                $this->ledger->record($locked, null, 'licence_grace_started', null, ['over_by' => $licensed - $capacity, 'until' => $until->toIso8601String()]);
                $result['grace_started'] = true;
            }

            return $result;
        });
    }

    /**
     * The scheduled pass. Safe to run at any time and as often as wanted.
     *
     * @return array{abandoned: int, to_grace: int, ended: int, rebalanced: int}
     */
    public function sweep(): array
    {
        $out = ['abandoned' => 0, 'to_grace' => 0, 'ended' => 0, 'rebalanced' => 0];
        $teams = [];   // team id => immediate
        $graceDays = max(1, (int) config('licensing.grace_days', 14));

        // A purchase that was started and never paid held nothing and is closed.
        LicenceBatch::where('status', LicenceBatch::PENDING)->where('created_at', '<', now()->subDays(max(1, (int) config('licensing.pending_batch_days', 7))))
            ->each(function (LicenceBatch $b) use (&$out) {
                $b->forceFill(['status' => LicenceBatch::ENDED])->save();
                $this->ledger->record($b->team, null, 'batch_ended', $b->uuid, ['reason' => 'never_paid']);
                $out['abandoned']++;
            });

        // A batch whose paid period is over: cancelled ones end (their people get the 14 days of D5); the rest wait in grace for Finance's word.
        LicenceBatch::where('status', LicenceBatch::ACTIVE)->whereNotNull('period_end')->get()->each(function (LicenceBatch $b) use (&$out, &$teams, $graceDays) {
            if ($b->covers()) {
                return;
            }

            DB::transaction(function () use ($b, &$out, &$teams, $graceDays) {
                $b = LicenceBatch::whereKey($b->id)->lockForUpdate()->first();

                if ($b === null || $b->status !== LicenceBatch::ACTIVE || $b->covers()) {
                    return;
                }

                if ($b->cancelled_at !== null) {
                    $b->forceFill(['status' => LicenceBatch::ENDED])->save();
                    $this->ledger->record($b->team, null, 'batch_ended', $b->uuid, ['reason' => 'cancelled_period_end']);
                    $out['ended']++;
                    $teams[$b->team_id] ??= false;
                } else {
                    $b->forceFill(['status' => LicenceBatch::GRACE, 'grace_until' => $b->period_end->copy()->addDays($graceDays)])->save();
                    $this->ledger->record($b->team, null, 'batch_payment_overdue', $b->uuid, ['grace_until' => $b->grace_until->toIso8601String()]);
                    $out['to_grace']++;
                    $teams[$b->team_id] ??= false;
                }
            });
        });

        // A batch whose grace has run out ends, and its people have already had their time.
        LicenceBatch::whereIn('status', [LicenceBatch::GRACE, LicenceBatch::REFUNDED])->whereNotNull('grace_until')->where('grace_until', '<=', now())->each(function (LicenceBatch $b) use (&$out, &$teams) {
            $b->forceFill(['status' => LicenceBatch::ENDED])->save();
            $this->ledger->record($b->team, null, 'batch_ended', $b->uuid, ['reason' => 'grace_ended']);
            $out['ended']++;
            $teams[$b->team_id] = true;
        });

        // Companies whose own grace has run out, or that have lapsed people who may now have room.
        TeamLicence::whereNotNull('grace_until')->where('grace_until', '<=', now())->pluck('team_id')->each(function ($id) use (&$teams) {
            $teams[(int) $id] ??= false;
        });
        DB::table('licence_assignments')->where('state', 'lapsed')->distinct()->pluck('team_id')->each(function ($id) use (&$teams) {
            $teams[(int) $id] ??= false;
        });

        foreach ($teams as $id => $immediate) {
            if ($team = Team::find($id)) {
                $this->rebalance($team, $immediate);
                $out['rebalanced']++;
            }
        }

        return $out;
    }
}
