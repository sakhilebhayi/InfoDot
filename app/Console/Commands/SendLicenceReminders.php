<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenceReminders;
use Illuminate\Console\Command;

/** R-53: renewal, failed-payment, shortfall and end-of-free-period reminders. Idempotent: running it twice sends nothing the second time. */
class SendLicenceReminders extends Command
{
    protected $signature = 'licence:remind';

    protected $description = 'Email licence reminders to owners and administrators (safe to run more than once)';

    public function handle(LicenceReminders $reminders): int
    {
        $done = $reminders->run();
        $this->info(sprintf('Sent %d renewal, %d payment, %d shortfall and %d free-period reminder(s).', $done['renewal'], $done['payment'], $done['shortfall'], $done['grandfather']));

        return self::SUCCESS;
    }
}
