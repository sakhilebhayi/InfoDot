<?php

namespace App\Console\Commands;

use App\Services\Reputation\RingDetector;
use Illuminate\Console\Command;

/** Raises advisory flags for groups of accounts that accept each other's answers. Counts only in the output. Does nothing while REPUTATION_ENABLED is off. */
class DetectReputationRings extends Command
{
    protected $signature = 'reputation:detect-rings';

    protected $description = 'Flag rings of accounts that accept each other\'s answers, for a moderator to review (only when REPUTATION_ENABLED)';

    public function handle(RingDetector $detector): int
    {
        if (! config('reputation.enabled') || ! config('reputation.rings.enabled', true)) {
            $this->warn('Reputation or ring checks are switched off; nothing detected.');

            return self::SUCCESS;
        }

        $r = $detector->detect();
        $this->info(sprintf('Looked at %d acceptance links: %d cycles and %d clusters found, %d new flags, %d already known.', $r['links'], $r['cycles'], $r['clusters'], $r['created'], $r['refreshed']));

        return self::SUCCESS;
    }
}
