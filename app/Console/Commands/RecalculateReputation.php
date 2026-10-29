<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Reputation\ReputationEngine;
use Illuminate\Console\Command;

/** Recalculates stored reputation snapshots. Counts only in the output. Does nothing while REPUTATION_ENABLED is off. */
class RecalculateReputation extends Command
{
    protected $signature = 'reputation:recalculate {--user= : one user id}';

    protected $description = 'Recalculate knowledge reputation snapshots (only when REPUTATION_ENABLED)';

    public function handle(ReputationEngine $engine): int
    {
        if (! config('reputation.enabled')) {
            $this->warn('Reputation is switched off (REPUTATION_ENABLED); nothing recalculated.');

            return self::SUCCESS;
        }

        $users = User::query()->when($this->option('user'), fn ($q, $id) => $q->whereKey($id));
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
}
