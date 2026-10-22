<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Knowledge\AchievementService;
use Illuminate\Console\Command;

/**
 * Works out the milestones of people who contributed before milestones existed (or repairs any a missed event left
 * behind). Safe to run again at any time: it only moves a person's milestones to what their current work earns.
 * Prints counts only, never names or addresses.
 */
class BackfillAchievements extends Command
{
    protected $signature = 'achievements:backfill {--user= : Only this user (id or uuid)}';

    protected $description = 'Award, take back or restore contribution milestones to match each person\'s current work';

    public function handle(AchievementService $achievements): int
    {
        $totals = ['users' => 0, 'awarded' => 0, 'revoked' => 0, 'restored' => 0];

        $each = function (User $user) use ($achievements, &$totals): void {
            $changes = $achievements->sync($user);
            $totals['users']++;
            $totals['awarded'] += $changes['awarded'];
            $totals['revoked'] += $changes['revoked'];
            $totals['restored'] += $changes['restored'];
        };

        if (($only = $this->option('user')) !== null) {
            $user = User::where(ctype_digit((string) $only) ? 'id' : 'uuid', $only)->first();

            if ($user === null) {
                $this->error('No such user.');

                return self::FAILURE;
            }

            $each($user);
        } else {
            User::query()->chunkById(200, fn ($users) => $users->each($each));
        }

        $this->info(sprintf('Checked %d users: %d awarded, %d restored, %d taken back.', $totals['users'], $totals['awarded'], $totals['restored'], $totals['revoked']));

        return self::SUCCESS;
    }
}
