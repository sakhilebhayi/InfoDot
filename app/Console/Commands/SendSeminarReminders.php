<?php

namespace App\Console\Commands;

use App\Services\Seminars\SeminarService;
use Illuminate\Console\Command;

/** In-app reminders, a day and an hour before a seminar, to people who registered for it. Counts only in the output. */
class SendSeminarReminders extends Command
{
    protected $signature = 'seminars:remind';

    protected $description = 'Send day-before and hour-before in-app reminders for seminars people registered for';

    public function handle(SeminarService $seminars): int
    {
        $this->info('Sent '.$seminars->sendReminders().' reminder(s).');

        return self::SUCCESS;
    }
}
