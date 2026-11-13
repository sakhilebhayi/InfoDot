<?php

namespace App\Console\Commands;

use App\Services\Platforms\Entitlements;
use Illuminate\Console\Command;

/**
 * Ends what time has ended (R-50 section 6): trials, dated complimentary grants, grace periods after a failed
 * payment, and cancelled subscriptions whose paid period is over. Scheduled every minute; each change is made once,
 * with its event and its message to the platform, however often this runs.
 */
class PlatformsLifecycle extends Command
{
    protected $signature = 'platforms:lifecycle {--limit=200}';

    protected $description = 'End trials, dated complimentary grants and grace periods that have run out';

    public function handle(Entitlements $entitlements): int
    {
        $done = $entitlements->runLifecycle((int) $this->option('limit'));

        $this->info(sprintf('Ended %d trial(s), %d dated grant(s), %d grace period(s) and %d paid period(s).', $done['trials'], $done['grants'], $done['grace'], $done['periods']));

        return self::SUCCESS;
    }
}
