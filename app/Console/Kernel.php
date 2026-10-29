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

        // Opt-in summaries of what people earned in the last closed week (Monday) or month (the 1st). The first run is at
        // 08:00 app time; the later hourly runs the same day only let a failed send be retried (the service waits 1 h, 2 h,
        // 4 h, 8 h between attempts) and are harmless otherwise: a digest is never emailed twice.
        $schedule->command('points:deliver-digests week')->cron('0 8-23 * * 1')->withoutOverlapping()->onOneServer();
        $schedule->command('points:deliver-digests month')->cron('0 8-23 1 * *')->withoutOverlapping()->onOneServer();

        // Participation events waiting for Dot.Memory (only for people who opted in; retried with growing delays).
        $schedule->command('participation:flush-outbox')->everyMinute()->withoutOverlapping()->onOneServer();

        // In-app notifications: read ones go after 90 days, any after a year.
        $schedule->command('reputation:recalculate')->dailyAt('03:40')->withoutOverlapping()->onOneServer();
        $schedule->command('notices:prune')->dailyAt('03:20')->withoutOverlapping()->onOneServer();

        // Interaction signal counts (R-12) are kept for the retention period only.
        $schedule->command('ux:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

        // What moderators decided about Dot.Brain's library recommendations, waiting to be reported to Dot.Memory (retried with growing delays).
        $schedule->command('library:flush-outcomes')->everyMinute()->withoutOverlapping()->onOneServer();
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
