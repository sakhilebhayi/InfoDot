<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Platforms\Moderators;
use Illuminate\Console\Command;

/** Takes the moderator role away from an account. Run on the host. The change is written to the audit trail. */
class ModeratorRevoke extends Command
{
    protected $signature = 'moderator:revoke {email : The email address of the account} {--force : Allow taking the role from the only moderator}';

    protected $description = 'Take the moderator role away from an account. Audited.';

    public function handle(Moderators $moderators): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email address. Nothing was changed.');

            return self::FAILURE;
        }

        try {
            $changed = $moderators->revoke($user, (bool) $this->option('force'));
        } catch (EntitlementRefused $e) {
            $this->error($e->getMessage().' Nothing was changed.');

            return self::FAILURE;
        }

        $this->info($changed ? "{$user->email} is no longer a moderator." : "{$user->email} is not a moderator. Nothing was changed.");

        return self::SUCCESS;
    }
}
