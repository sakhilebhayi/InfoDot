<?php

namespace App\Console\Commands;

use App\Services\Verification\VerificationService;
use Illuminate\Console\Command;

/**
 * Daily verification housekeeping (R-29 §13, §25): verifications past their date lapse and lose
 * the badge, owners are reminded before that happens, and evidence of finished applications is
 * deleted once its retention period ends.
 */
class SweepBusinessVerifications extends Command
{
    protected $signature = 'business:verifications';

    protected $description = 'Lapse expired business verifications, send renewal reminders and delete evidence past retention';

    public function handle(VerificationService $verification): int
    {
        $expiry = $verification->sweepExpiry();
        $purged = $verification->purgeEvidence();

        $this->info("Lapsed {$expiry['expired']}, reminded {$expiry['reminded']}, evidence purged for {$purged} application(s).");

        return self::SUCCESS;
    }
}
