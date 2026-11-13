<?php

namespace App\Console\Commands;

use App\Models\PlatformCatalogueEvent;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way to make (or unmake) a platform administrator (R-50): from a shell on the host, never from a screen.
 * A platform administrator manages the catalogue, prices and complimentary access; a moderator does not. Every grant
 * and revocation is written to the catalogue audit trail.
 */
class PlatformAdmin extends Command
{
    protected $signature = 'platform:admin {action : grant | revoke} {email : The email address of the account}';

    protected $description = 'Grant or revoke the platform administrator role (catalogue, prices, complimentary access)';

    public function handle(): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['grant', 'revoke'], true)) {
            $this->error('The action is grant or revoke. Nothing was changed.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email address. Nothing was changed.');

            return self::FAILURE;
        }

        $was = $user->isPlatformAdmin();
        $now = $action === 'grant';

        if ($was === $now) {
            $this->info("{$user->email} ".($now ? 'is already a platform administrator.' : 'is not a platform administrator.').' Nothing was changed.');

            return self::SUCCESS;
        }

        $user->forceFill(['is_platform_admin' => $now])->save();

        PlatformCatalogueEvent::create([
            'actor_id' => null, 'subject_type' => 'administrator', 'subject_key' => (string) $user->uuid,
            'type' => $now ? 'administrator_granted' : 'administrator_revoked',
            'before' => ['is_platform_admin' => $was], 'after' => ['is_platform_admin' => $now], 'created_at' => now(),
        ]);

        $this->info("{$user->email} ".($now ? 'is now a platform administrator.' : 'is no longer a platform administrator.'));

        return self::SUCCESS;
    }
}
