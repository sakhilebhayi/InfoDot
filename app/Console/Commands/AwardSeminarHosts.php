<?php

namespace App\Console\Commands;

use App\Services\Seminars\SeminarHostPoints;
use Illuminate\Console\Command;

/** Queues Dot Points events for hosts whose seminars finished well attended and rated, once each. Counts only. Does nothing while SEMINARS_POINTS_ENABLED is off. */
class AwardSeminarHosts extends Command
{
    protected $signature = 'seminars:award-hosts';

    protected $description = 'Queue Dot Points for hosts of well attended, well rated seminars (only when SEMINARS_POINTS_ENABLED)';

    public function handle(SeminarHostPoints $points): int
    {
        if (! $points->enabled()) {
            $this->warn('Points for hosting seminars are switched off (SEMINARS_POINTS_ENABLED); nothing queued.');

            return self::SUCCESS;
        }

        $r = $points->run();
        $this->info(sprintf('Checked %d seminars: %d awards queued, %d reversals queued.', $r['checked'], $r['awarded'], $r['reversed']));

        return self::SUCCESS;
    }
}
