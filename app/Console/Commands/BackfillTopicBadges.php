<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Knowledge\AchievementService;
use App\Services\Knowledge\TopicBadges;
use Illuminate\Console\Command;

/**
 * Works out the specialist badges of people who contributed before badges existed, or after the levels in
 * config/achievements.php changed (raise `badges.version` too). Safe to run again at any time: it only moves a person's badges to what
 * their current work earns, and a second run changes nothing. Prints counts only, never names or addresses.
 */
class BackfillTopicBadges extends Command
{
    protected $signature = 'badges:backfill {--user= : Only this user (id or uuid)}';

    protected $description = 'Award, change or take back specialist topic badges to match each person\'s current work';

    public function handle(AchievementService $achievements, TopicBadges $badges): int
    {
        $totals = ['users' => 0, 'awarded' => 0, 'changed' => 0, 'removed' => 0];

        $each = function (User $user) use ($achievements, $badges, &$totals): void {
            $changes = $badges->sync($user, $achievements->workByTopic($user));
            $totals['users']++;
            foreach ($changes as $k => $n) {
                $totals[$k] += $n;
            }
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

        $this->info(sprintf('Checked %d users: %d awarded, %d changed, %d taken back.', $totals['users'], $totals['awarded'], $totals['changed'], $totals['removed']));

        return self::SUCCESS;
    }
}
