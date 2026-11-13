<?php

namespace App\Console\Commands;

use App\Services\Platforms\EntitlementOutbox;
use Illuminate\Console\Command;

/** Sends waiting entitlement changes to their platforms (R-50). Scheduled every minute; see EntitlementOutbox for the retry rules. */
class FlushEntitlementOutbox extends Command
{
    protected $signature = 'platforms:flush-outbox {--limit=50}';

    protected $description = 'Tell each platform about waiting entitlement changes (retries with growing delays)';

    public function handle(EntitlementOutbox $outbox): int
    {
        $this->info('Delivered '.$outbox->flush((int) $this->option('limit')).' event(s).');

        return self::SUCCESS;
    }
}
