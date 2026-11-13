<?php

namespace App\Console\Commands;

use App\Services\Ops\OpsCheck;
use Illuminate\Console\Command;

/** Looks for silent failures and reports them. Counts only in the output; exits 1 when something is wrong so the cron log shows it. */
class OpsCheckCommand extends Command
{
    protected $signature = 'ops:check';

    protected $description = 'Report parked outbox events, unconfirmed refunds, unreleased holds, unwritable folders, low disk space and logged errors (counts only)';

    public function handle(OpsCheck $ops): int
    {
        $found = $ops->run();
        $ops->alert($found);

        foreach ($found as $kind => $count) {
            $this->line(($count > 0 ? 'PROBLEM ' : 'ok      ').str_pad($kind, 34).$count);
        }

        foreach ($ops->warnings() as $kind => $count) {
            $this->line(($count > 0 ? 'warn    ' : 'ok      ').str_pad($kind, 34).$count);
        }

        return array_sum($found) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
