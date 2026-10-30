<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Reputation\ReputationEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Recalculates stored reputation snapshots. Counts only in the output. Does nothing while REPUTATION_ENABLED is off.
 *
 * Plain `reputation:recalculate` does everyone at once. The scheduler runs it with `--resume`: the host kills a whole `schedule:run` pass after
 * 115 seconds, and about 25 queries per person does not fit once there are enough people, so each run works for at most `--budget` seconds,
 * remembers where it stopped, and the next run (ten minutes later, overnight) carries on. When everyone has been done it waits for the next day.
 */
class RecalculateReputation extends Command
{
    protected $signature = 'reputation:recalculate {--user= : one user id} {--resume : continue an overnight pass from where the last run stopped} {--budget=80 : seconds a --resume run may work}';

    protected $description = 'Recalculate knowledge reputation snapshots (only when REPUTATION_ENABLED)';

    public function handle(ReputationEngine $engine): int
    {
        if (! config('reputation.enabled')) {
            $this->warn('Reputation is switched off (REPUTATION_ENABLED); nothing recalculated.');

            return self::SUCCESS;
        }

        if ($this->option('resume') && ! $this->option('user')) {
            return $this->resume($engine);
        }

        $users = User::query()->where('reputation_opt_out', false)->when($this->option('user'), fn ($q, $id) => $q->whereKey($id));
        $n = 0;
        $users->chunkById(200, function ($chunk) use ($engine, &$n) {
            foreach ($chunk as $u) {
                $engine->snapshot($u);
                $n++;
            }
        });
        $this->info("Recalculated {$n} person/people.");

        return self::SUCCESS;
    }

    private function resume(ReputationEngine $engine): int
    {
        $today = now()->toDateString();

        if (Cache::get('reputation:done') === $today) {
            $this->info('Already done today.');

            return self::SUCCESS;
        }

        $deadline = microtime(true) + max(5, (int) $this->option('budget'));
        $cursor = (int) Cache::get('reputation:cursor', 0);
        $n = 0;

        while (microtime(true) < $deadline) {
            $batch = User::query()->where('reputation_opt_out', false)->where('id', '>', $cursor)->orderBy('id')->limit(50)->get();

            if ($batch->isEmpty()) {
                Cache::put('reputation:done', $today, 86400);
                Cache::forget('reputation:cursor');
                $engine->pruneSnapshots();
                $this->info("Recalculated {$n} person/people; the pass is complete.");

                return self::SUCCESS;
            }

            foreach ($batch as $u) {
                $engine->snapshot($u);
                $cursor = $u->id;
                $n++;

                if (microtime(true) >= $deadline) {
                    break;
                }
            }
        }

        Cache::put('reputation:cursor', $cursor, 86400);
        $this->info("Recalculated {$n} person/people; will continue after user {$cursor}.");

        return self::SUCCESS;
    }
}
