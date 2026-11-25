<?php

namespace App\Console\Commands;

use App\Services\Notifications\NoticeDigests;
use Illuminate\Console\Command;

class SendNoticeDigests extends Command
{
    protected $signature = 'notices:digest';

    protected $description = 'Send the daily notification summary to people who chose it (R-64)';

    public function handle(NoticeDigests $digests): int
    {
        $this->info('Summaries sent: '.$digests->run());

        return self::SUCCESS;
    }
}
