<?php

namespace Tests\Feature\Hardening;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * The cron runner kills a pass after 115 seconds. A killed `schedule:run` never releases its overlap lock, and Laravel's default lock lasts 24 hours,
 * so one slow run would silence that task for a day (points delivery, refund retries). Every task's lock must expire within half an hour.
 */
class ScheduleSafetyTest extends TestCase
{
    public function test_every_scheduled_task_that_blocks_overlap_lets_go_of_its_lock_within_half_an_hour(): void
    {
        $events = collect(app(Schedule::class)->events());
        $this->assertGreaterThan(10, $events->count());

        foreach ($events as $event) {
            if ($event->withoutOverlapping) {
                $this->assertLessThanOrEqual(30, $event->expiresAt, 'the lock of "'.$event->command.'" would outlive a killed run');
            }
        }
    }
}
