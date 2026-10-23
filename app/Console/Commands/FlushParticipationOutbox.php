<?php

namespace App\Console\Commands;

use App\Services\Participation\ParticipationOutbox;
use Illuminate\Console\Command;

class FlushParticipationOutbox extends Command
{
    protected $signature = 'participation:flush-outbox {--limit=50}';

    protected $description = 'Send waiting participation events to Dot.Memory for people who opted in (retries with growing delays)';

    public function handle(ParticipationOutbox $outbox): int
    {
        $this->info('Delivered '.$outbox->flush((int) $this->option('limit')).' event(s).');
        $outbox->prune();

        return self::SUCCESS;
    }
}
