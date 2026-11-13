<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use Illuminate\Console\Command;

/** Undoes account:suspend. The person signs in again from scratch; nothing that was ended is brought back. */
class AccountRestore extends Command
{
    protected $signature = 'account:restore {email : The email address of the account} {--reason= : Why, kept in the suspension trail}';

    protected $description = 'Restore a suspended account so the person can sign in again';

    public function handle(AccountSuspension $suspension): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email address. Nothing was changed.');

            return self::FAILURE;
        }

        if (! $user->isSuspended()) {
            $this->info("{$user->email} is not suspended. Nothing was changed.");

            return self::SUCCESS;
        }

        $suspension->restore($user, $this->option('reason'));

        $this->info("{$user->email} is restored and can sign in again.");

        return self::SUCCESS;
    }
}
