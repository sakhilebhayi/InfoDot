<?php

namespace App\Console\Commands;

use App\Services\Ux\FrictionReport;
use App\Services\Ux\UxSignals;
use Illuminate\Console\Command;

/** Prints where people struggle, from the daily counts (counts and route templates only). Advisory. */
class UxFriction extends Command
{
    protected $signature = 'ux:friction {--days=14 : How many days to look at}';

    protected $description = 'Show the friction findings from the interaction signal counts (advisory)';

    public function handle(FrictionReport $report, UxSignals $signals): int
    {
        if (! $signals->enabled()) {
            $this->warn('Interaction signals are off (UX_SIGNALS is not true), so there is nothing new to read.');
        }

        $r = $report->build((int) $this->option('days'));
        $this->info("Friction findings {$r['from']} to {$r['to']}: {$r['totals']['events']} counted events on {$r['totals']['routes']} routes.");

        if ($r['findings'] === []) {
            $this->line('No rule fired. (A rule needs at least '.config('ux.rules.min_events').' observations before it says anything.)');

            return self::SUCCESS;
        }

        $this->table(['Route', 'Rule', 'Events', 'Of', 'Rate', 'Before', 'Change', 'Trust'], array_map(fn ($f) => [
            $f['route'], $f['rule'], $f['events'], $f['of'], number_format($f['rate'] * 100, 1).'%',
            $f['previous_rate'] === null ? '-' : number_format($f['previous_rate'] * 100, 1).'%', $f['change'] ?? '-', $f['confidence'],
        ], $r['findings']));

        return self::SUCCESS;
    }
}
