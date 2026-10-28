<?php

namespace App\Console\Commands;

use App\Services\Ux\UxSignals;
use Illuminate\Console\Command;

/** Removes interaction counts older than the retention period (config/ux.php, 180 days by default). */
class PruneUxSignals extends Command
{
    protected $signature = 'ux:prune {--days= : Keep this many days (at least 7)}';

    protected $description = 'Delete old interaction signal counts';

    public function handle(UxSignals $signals): int
    {
        $days = $this->option('days');
        $this->info('Removed '.$signals->prune($days === null ? null : (int) $days).' old count row(s).');

        return self::SUCCESS;
    }
}
