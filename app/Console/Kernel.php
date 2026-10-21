<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Contribution events waiting for the Dot Points ledger (retried with growing delays; see RewardOutbox).
        $schedule->command('points:flush-outbox')->everyMinute()->withoutOverlapping()->onOneServer();

        // Emails people before some of their points lapse (the points service pulls no addresses; see the command).
        $schedule->command('points:deliver-expiry-warnings')->hourly()->withoutOverlapping()->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
