<?php

namespace App\Console\Commands;

use App\Services\Knowledge\InAppNotices;
use Illuminate\Console\Command;

/** Removes read notices after 90 days and any notice after a year. Counts only in the output. */
class PruneNotices extends Command
{
    protected $signature = 'notices:prune {--read-days=90} {--any-days=365}';

    protected $description = 'Delete old in-app notifications (read ones after 90 days, any after a year)';

    public function handle(InAppNotices $notices): int
    {
        $removed = $notices->prune(max(1, (int) $this->option('read-days')), max(1, (int) $this->option('any-days')));
        $this->info("Removed {$removed} old notification(s).");

        return self::SUCCESS;
    }
}
