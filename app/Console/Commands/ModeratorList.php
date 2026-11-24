<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Lists who holds the moderator and platform administrator roles, so access can be reviewed. Read-only. */
class ModeratorList extends Command
{
    protected $signature = 'roles:list';

    protected $description = 'List the accounts that are moderators or platform administrators (read-only)';

    public function handle(): int
    {
        $rows = User::query()->where(fn ($q) => $q->where('is_moderator', true)->orWhere('is_platform_admin', true))->orderBy('id')->get()
            ->map(fn (User $u) => [$u->email, $u->isPlatformAdmin() ? 'yes' : 'no', $u->isModerator() ? 'yes' : 'no', $u->isSuspended() ? 'suspended' : 'active']);

        if ($rows->isEmpty()) {
            $this->info('Nobody holds either role.');

            return self::SUCCESS;
        }

        $this->table(['Email', 'Platform administrator', 'Moderator', 'State'], $rows->all());

        return self::SUCCESS;
    }
}
