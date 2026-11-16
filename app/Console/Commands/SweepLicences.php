<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenceLifecycle;
use Illuminate\Console\Command;

/** R-53: moves paid licence batches along by their dates (grace, end) and applies the lapse and reactivation rules. Nothing is ever deleted. Safe to run often. */
class SweepLicences extends Command
{
    protected $signature = 'licence:sweep';

    protected $description = 'End licence batches that have run out and lapse or restore licences by rule';

    public function handle(LicenceLifecycle $lifecycle): int
    {
        $done = $lifecycle->sweep();
        $this->info(sprintf('Closed %d unpaid purchase(s), moved %d batch(es) to grace, ended %d, rebalanced %d company(ies).', $done['abandoned'], $done['to_grace'], $done['ended'], $done['rebalanced']));

        return self::SUCCESS;
    }
}
