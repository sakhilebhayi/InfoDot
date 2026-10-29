<?php

namespace App\Console\Commands;

use App\Services\Privacy\SeminarRecordPruner;
use Illuminate\Console\Command;

/** Applies the retention periods in config/seminars.php. Counts only in the output; --dry changes nothing. */
class PruneSeminarRecords extends Command
{
    protected $signature = 'seminars:prune {--dry : only count what would be removed}';

    protected $description = 'Delete old seminar records on the retention schedule (room data, sent points rows, closed reports, money records of deleted accounts)';

    public function handle(SeminarRecordPruner $pruner): int
    {
        $dry = (bool) $this->option('dry');

        foreach ($pruner->run($dry) as $kind => $count) {
            $this->line(($dry ? 'would remove ' : 'removed ').$count.' '.str_replace('_', ' ', $kind));
        }

        return self::SUCCESS;
    }
}
