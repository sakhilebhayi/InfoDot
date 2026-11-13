<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Platforms\PlatformAdministrators;

/** The one piece of work behind the three platform administrator commands (R-50): find the account, change the role, say what happened. */
trait ChangesPlatformAdministrator
{
    protected function change(PlatformAdministrators $administrators, bool $grant, string $email): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('No account has that email address. Nothing was changed.');

            return self::FAILURE;
        }

        try {
            $changed = $grant ? $administrators->grant($user) : $administrators->revoke($user);
        } catch (EntitlementRefused $e) {
            $this->error($e->getMessage().' Nothing was changed.');

            return self::FAILURE;
        }

        if (! $changed) {
            $this->info("{$user->email} ".($grant ? 'is already a platform administrator.' : 'is not a platform administrator.').' Nothing was changed.');

            return self::SUCCESS;
        }

        $this->info("{$user->email} ".($grant ? 'is now a platform administrator.' : 'is no longer a platform administrator.'));

        return self::SUCCESS;
    }
}
