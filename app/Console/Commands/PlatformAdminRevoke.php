<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ChangesPlatformAdministrator;
use App\Services\Platforms\PlatformAdministrators;
use Illuminate\Console\Command;

/** Takes the platform administrator role away from an account (R-50). Run on the host. */
class PlatformAdminRevoke extends Command
{
    use ChangesPlatformAdministrator;

    protected $signature = 'platform-admin:revoke {email : The email address of the account}';

    protected $description = 'Take the platform administrator role away from an account';

    public function handle(PlatformAdministrators $administrators): int
    {
        return $this->change($administrators, false, (string) $this->argument('email'));
    }
}
