<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChangesPlatformAdministrator;
use App\Services\Platforms\PlatformAdministrators;
use Illuminate\Console\Command;

/**
 * Makes an account a platform administrator (R-50). Run on the host; there is no screen for this. An account that
 * has not verified its email address, or that is suspended, is refused.
 */
class PlatformAdminGrant extends Command
{
    use ChangesPlatformAdministrator;

    protected $signature = 'platform-admin:grant {email : The email address of the account}';

    protected $description = 'Make an account a platform administrator (catalogue, prices, bundles, complimentary access)';

    public function handle(PlatformAdministrators $administrators): int
    {
        return $this->change($administrators, true, (string) $this->argument('email'));
    }
}
