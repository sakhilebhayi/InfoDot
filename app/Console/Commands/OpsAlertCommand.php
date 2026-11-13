<?php

namespace App\Console\Commands;

use App\Services\Ops\OpsAlert;
use Illuminate\Console\Command;

/**
 * Every ten minutes: email OPS_ALERT_ADDRESS when the log gained error lines or an operations check is not ok (see OpsAlert).
 * Always exits 0: a failed send is logged and retried by the next run, and a red scheduler entry would say nothing more.
 */
class OpsAlertCommand extends Command
{
    protected $signature = 'ops:alert';

    protected $description = 'Email the operations address about new log errors and failing checks (at most once an hour)';

    public function handle(OpsAlert $alert): int
    {
        $this->line('ops:alert '.$alert->run());

        return self::SUCCESS;
    }
}
