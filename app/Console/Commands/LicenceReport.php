<?php

namespace App\Console\Commands;

use App\Models\LicenceAssignment;
use App\Models\LicenceBatch;
use App\Models\Team;
use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\SeatLedger;
use Illuminate\Console\Command;

/**
 * R-53: a read-only report of every company's licence state, for whoever runs the platform. Writes nothing, anywhere. `--problems` lists only
 * companies that need a person (over capacity, in a grace, or with an active batch that has no activation record).
 */
class LicenceReport extends Command
{
    protected $signature = 'licence:report {--team= : One company, by uuid} {--problems : Only companies that need attention} {--json : Machine-readable output}';

    protected $description = 'Read-only report of each company\'s licence state (nothing is changed)';

    public function handle(SeatLedger $ledger, LicenceAssignments $assignments): int
    {
        $rows = [];

        Team::where('personal_team', false)->when($this->option('team'), fn ($q, $u) => $q->where('uuid', strtolower((string) $u)))->orderBy('id')->each(function (Team $team) use (&$rows, $ledger, $assignments) {
            $s = $ledger->summary($team);
            $batches = LicenceBatch::where('team_id', $team->getKey());
            $row = [
                'company' => $team->name, 'uuid' => (string) $team->uuid, 'free' => $s['included'], 'paid' => $s['purchased'], 'capacity' => $s['capacity'], 'people' => $s['people'],
                'reserved' => $s['reserved'], 'available' => $s['available'], 'over_by' => $s['over_by'],
                'batches_active' => (clone $batches)->where('status', LicenceBatch::ACTIVE)->count(), 'batches_grace' => (clone $batches)->whereIn('status', [LicenceBatch::GRACE, LicenceBatch::REFUNDED])->count(),
                'batches_pending' => (clone $batches)->where('status', LicenceBatch::PENDING)->count(),
                'lapsed' => LicenceAssignment::where('team_id', $team->getKey())->where('state', LicenceAssignment::LAPSED)->count(),
                'released' => LicenceAssignment::where('team_id', $team->getKey())->where('state', LicenceAssignment::RELEASED)->count(),
                'grace_until' => $s['grace_until']?->toDateString(), 'grandfathered_until' => $s['grandfathered_until']?->toDateString(), 'licensed' => $assignments->licensedCount($team),
            ];
            $row['needs_attention'] = $row['over_by'] > 0 || $row['batches_grace'] > 0 || $row['grace_until'] !== null || $row['lapsed'] > 0;

            if (! $this->option('problems') || $row['needs_attention']) {
                $rows[] = $row;
            }
        });

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Company', 'Free', 'Paid', 'Cap', 'People', 'Held', 'Avail', 'Over', 'Act', 'Grace', 'Pend', 'Lapsed', 'Rel', 'Grace until', 'Free until'], array_map(fn ($r) => [
            $r['company'], $r['free'], $r['paid'], $r['capacity'], $r['people'], $r['reserved'], $r['available'], $r['over_by'], $r['batches_active'], $r['batches_grace'], $r['batches_pending'], $r['lapsed'], $r['released'], $r['grace_until'] ?? '-', $r['grandfathered_until'] ?? '-',
        ], $rows));
        $this->info(count($rows).' company(ies).');

        return self::SUCCESS;
    }
}
