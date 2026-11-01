<?php

namespace App\Console\Commands;

use App\Services\Identity\IdentityProtection;
use App\Services\Verification\VerificationService;
use Illuminate\Console\Command;

/**
 * Daily verification housekeeping (R-29 §13, §25): verifications past their date lapse and lose
 * the badge, owners are reminded before that happens, and evidence of finished applications is
 * deleted once its retention period ends. Every verified business is checked to have an active
 * protected identity (R-29 Part C), which also covers businesses verified before it existed.
 */
class SweepBusinessVerifications extends Command
{
    protected $signature = 'business:verifications';

    protected $description = 'Lapse expired business verifications, send renewal reminders and delete evidence past retention';

    public function handle(VerificationService $verification, IdentityProtection $identity): int
    {
        $expiry = $verification->sweepExpiry();
        $purged = $verification->purgeEvidence();
        $protected = $identity->ensureProtected();

        $this->info("Lapsed {$expiry['expired']}, reminded {$expiry['reminded']}, evidence purged for {$purged} application(s), {$protected} identity record(s) created.");

        return self::SUCCESS;
    }
}
