<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ReservedEmailDomains;
use Illuminate\Console\Command;

/**
 * READ-ONLY. Lists existing users whose address is on a reserved or non-deliverable domain (example, test, invalid,
 * localhost, example.com/.net/.org), so the owner can decide what to do. It changes and deletes nothing, and it
 * prints addresses masked.
 */
class ReservedEmailUsers extends Command
{
    protected $signature = 'users:reserved-emails {--count : Print only the number of matching users}';

    protected $description = 'List (read-only) existing users whose email is on a reserved domain such as example.test';

    public function handle(): int
    {
        $rows = [];
        $total = 0;

        User::query()->select(['id', 'email', 'created_at'])->lazyById(500)->each(function (User $user) use (&$rows, &$total) {
            if (ReservedEmailDomains::isReserved($user->email)) {
                $total++;
                $rows[] = [$user->id, self::mask((string) $user->email), (string) $user->created_at];
            }
        });

        if ($this->option('count')) {
            $this->line((string) $total);

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('No existing user has an address on a reserved domain.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Email (masked)', 'Created'], $rows);
        $this->line("{$total} user(s). Nothing was changed.");

        return self::SUCCESS;
    }

    private static function mask(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return '***';
        }

        $local = substr($email, 0, $at);

        return mb_substr($local, 0, 1).str_repeat('*', max(2, min(6, mb_strlen($local) - 1))).substr($email, $at);
    }
}
