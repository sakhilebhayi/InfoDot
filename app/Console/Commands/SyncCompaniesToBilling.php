<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Licensing\BillingCompanies;
use App\Services\Licensing\BillingEntitlements;
use Illuminate\Console\Command;

/** Tells Dot.Billing about every existing company once (new ones are sent as they are created). Safe to repeat: Billing treats a repeat as no change. */
class SyncCompaniesToBilling extends Command
{
    protected $signature = 'licence:sync-companies {--dry-run : count only}';

    protected $description = 'Tell Dot.Billing about every existing company';

    public function handle(BillingCompanies $companies, BillingEntitlements $entitlements): int
    {
        if (! $entitlements->configured()) {
            $this->warn('The Billing link is not switched on (LICENCE_BILLING_CONNECTED, LICENCE_BILLING_URL, BILLING_ENTITLEMENT_SECRET); nothing sent.');

            return self::SUCCESS;
        }

        $sent = $failed = 0;
        Team::where('personal_team', false)->whereNotNull('uuid')->orderBy('id')->chunkById(100, function ($teams) use ($companies, &$sent, &$failed) {
            foreach ($teams as $team) {
                if ($this->option('dry-run')) {
                    $sent++;

                    continue;
                }

                $companies->sync($team) ? $sent++ : $failed++;
            }
        });

        $this->info(($this->option('dry-run') ? 'Would send ' : 'Sent ')."{$sent} company record(s); {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
