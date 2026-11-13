<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use Illuminate\Console\Command;

/**
 * The owner's way to switch an account off across the ecosystem (there is no screen for it yet). Says what was ended
 * so the person running it can see the suspension took hold.
 */
class AccountSuspend extends Command
{
    protected $signature = 'account:suspend {email : The email address of the account} {--reason= : Why, kept in the suspension trail}';

    protected $description = 'Suspend an account: it cannot sign in to InfoDot or any Dot platform until restored';

    public function handle(AccountSuspension $suspension): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email address. Nothing was changed.');

            return self::FAILURE;
        }

        $already = $user->isSuspended();
        $done = $suspension->suspend($user, $this->option('reason'));

        $this->info($already
            ? "{$user->email} was already suspended; the clean-up was run again."
            : "{$user->email} is now suspended.");
        $this->line("Browser sessions ended: {$done['sessions']}");
        $this->line("API tokens deleted: {$done['api_tokens']}");
        $this->line("Provider tokens revoked: {$done['provider_tokens']}");
        $this->line("Platforms told to sign the person out: {$done['platforms']}");

        return self::SUCCESS;
    }
}
