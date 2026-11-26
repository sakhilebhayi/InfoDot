<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Platforms\Moderators;
use Illuminate\Console\Command;

/** Makes an account a moderator. Run on the host; there is no screen for this. The change is written to the audit trail. */
class ModeratorGrant extends Command
{
    protected $signature = 'moderator:grant {email : The email address of the account}';

    protected $description = 'Make an account a moderator (moderation pages, reviews of other businesses). Audited.';

    public function handle(Moderators $moderators): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email address. Nothing was changed.');

            return self::FAILURE;
        }

        try {
            $changed = $moderators->grant($user);
        } catch (EntitlementRefused $e) {
            $this->error($e->getMessage().' Nothing was changed.');

            return self::FAILURE;
        }

        $this->info($changed ? "{$user->email} is now a moderator." : "{$user->email} is already a moderator. Nothing was changed.");
        if (! $user->fresh()?->hasSecuredSignIn()) {
            // R-72 (S-02): the role is in place, but it does nothing until the person signs in with two-factor.
            $this->warn('Their moderation powers work only once they turn on two-factor sign-in (Account settings, Two factor).');
        }

        return self::SUCCESS;
    }
}
