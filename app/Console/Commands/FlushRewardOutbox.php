<?php

namespace App\Console\Commands;

use App\Services\Points\RewardOutbox;
use Illuminate\Console\Command;

class FlushRewardOutbox extends Command
{
    protected $signature = 'points:flush-outbox {--limit=50}';

    protected $description = 'Send waiting contribution events to the Dot Points ledger (retries with growing delays)';

    public function handle(RewardOutbox $outbox): int
    {
        $this->info('Delivered '.$outbox->flush((int) $this->option('limit')).' event(s).');

        return self::SUCCESS;
    }
}
