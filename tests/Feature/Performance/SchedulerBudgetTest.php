<?php

namespace Tests\Feature\Performance;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * R-72 (P-29, P-30): the every-minute jobs run side by side instead of one after another inside the host's kill window, a job
 * that was killed is free to run again after five minutes rather than thirty, and the database cache's expired rows are removed.
 */
class SchedulerBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $needle): Event
    {
        return collect(app(Schedule::class)->events())->first(fn (Event $e) => str_contains((string) ($e->command ?? $e->description), $needle))
            ?? $this->fail("no scheduled event for $needle");
    }

    public function test_the_minute_jobs_run_in_the_background_with_a_short_lock(): void
    {
        foreach (['points:flush-outbox', 'participation:flush-outbox', 'library:flush-outcomes', 'platforms:flush-outbox', 'platforms:lifecycle'] as $command) {
            $event = $this->event($command);
            $this->assertSame('* * * * *', $event->expression, $command);
            $this->assertTrue($event->runInBackground, "$command runs in the background");
            $this->assertTrue($event->withoutOverlapping, "$command never overlaps itself");
            $this->assertSame(5, $event->expiresAt, "$command is free again five minutes after a kill");
        }
    }

    public function test_expired_cache_rows_are_removed_and_live_ones_kept(): void
    {
        $this->assertTrue(Schema::hasTable('cache') && Schema::hasTable('cache_locks'));
        DB::table('cache')->insert([
            ['key' => 'old', 'value' => 's:1:"x";', 'expiration' => now()->subHour()->getTimestamp()],
            ['key' => 'live', 'value' => 's:1:"y";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        config(['cache.default' => 'database']);
        $this->event('cache:prune-expired')->run($this->app);

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
    }
}
