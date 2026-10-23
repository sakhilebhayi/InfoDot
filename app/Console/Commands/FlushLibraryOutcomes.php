<?php

namespace App\Console\Commands;

use App\Services\Library\LibraryOutcomeOutbox;
use Illuminate\Console\Command;

class FlushLibraryOutcomes extends Command
{
    protected $signature = 'library:flush-outcomes {--limit=50}';

    protected $description = 'Tell Dot.Memory what moderators decided about its library recommendations (retries with growing delays)';

    public function handle(LibraryOutcomeOutbox $outbox): int
    {
        $this->info('Delivered '.$outbox->flush((int) $this->option('limit')).' outcome(s).');

        return self::SUCCESS;
    }
}
