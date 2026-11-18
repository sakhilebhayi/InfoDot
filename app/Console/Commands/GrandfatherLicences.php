<?php

namespace App\Console\Commands;

use App\Services\Licensing\Grandfathering;
use Illuminate\Console\Command;

/**
 * R-53: freeze every existing company at its current headcount for a transition period, or undo that. A DRY RUN unless --apply is given.
 * Nobody is removed and nothing is deleted.
 */
class GrandfatherLicences extends Command
{
    protected $signature = 'licence:grandfather
        {--apply : Actually write the changes (without it, only a report is printed)}
        {--rollback : Restore the rows as they were before the last --apply (also a report unless --apply is given)}
        {--days= : How many days the frozen headcount stays free (default licensing.grandfather_days, 90)}
        {--team=* : Only these company ids}';

    protected $description = 'Freeze existing companies at their current headcount for the R-53 transition (dry run unless --apply)';

    public function handle(Grandfathering $service): int
    {
        $apply = (bool) $this->option('apply');
        $teams = array_map('intval', (array) $this->option('team'));
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        if ($days !== null && $days < 1) {
            $this->error('--days must be at least 1.');

            return self::INVALID;
        }

        if ($this->option('rollback')) {
            $rows = $apply ? $service->rollback($teams) : $service->rollbackPlan($teams);
            $this->table(['company', 'included now', 'restored to'], array_map(fn ($r) => [$r['team_id'], $r['current_included'] ?? 'default', $r['restore_included'] ?? 'default'], $rows));
            $this->line($apply ? count($rows).' company row(s) restored. Nobody was added or removed.' : 'DRY RUN: '.count($rows).' company row(s) would be restored. Run again with --apply to do it.');

            return self::SUCCESS;
        }

        $rows = $apply ? $service->apply($teams, $days) : $service->plan($teams);
        $freeze = array_values(array_filter($rows, fn ($r) => $r['action'] === 'freeze'));

        $this->table(['company', 'name', 'people', 'bought', 'included now', 'included after', 'action'],
            array_map(fn ($r) => [$r['team_id'], mb_substr($r['name'], 0, 30), $r['headcount'], $r['purchased'], $r['current_included'] ?? 'default', $r['new_included'] ?? 'default', $r['action']], $rows));
        $days ??= (int) config('licensing.grandfather_days', 90);

        $this->line($apply
            ? count($freeze)." company(ies) frozen for {$days} days. Nobody was removed."
            : 'DRY RUN: '.count($freeze)." company(ies) would be frozen for {$days} days. Nothing was written. Run again with --apply to do it.");

        return self::SUCCESS;
    }
}
