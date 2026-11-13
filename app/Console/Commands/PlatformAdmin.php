<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChangesPlatformAdministrator;
use App\Services\Platforms\PlatformAdministrators;
use Illuminate\Console\Command;

/**
 * The only way to make (or unmake) a platform administrator (R-50): from a shell on the host, never from a screen.
 * A platform administrator manages the catalogue, prices and complimentary access; a moderator does not. Every grant
 * and revocation is written to the catalogue audit trail. `platform-admin:grant` and `platform-admin:revoke` are the
 * documented names; this older two-argument form does the same thing.
 */
class PlatformAdmin extends Command
{
    use ChangesPlatformAdministrator;

    protected $signature = 'platform:admin {action : grant | revoke} {email : The email address of the account}';

    protected $description = 'Grant or revoke the platform administrator role (same as platform-admin:grant and platform-admin:revoke)';

    public function handle(PlatformAdministrators $administrators): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['grant', 'revoke'], true)) {
            $this->error('The action is grant or revoke. Nothing was changed.');

            return self::FAILURE;
        }

        return $this->change($administrators, $action === 'grant', (string) $this->argument('email'));
    }
}
