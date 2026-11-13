<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Licensing\FinanceCompanies;
use App\Services\Licensing\FinanceEntitlements;
use Illuminate\Console\Command;

/** Tells Dot.Finance about every existing company once (new ones are sent as they are created). Safe to repeat: Finance treats a repeat as no change. */
class SyncCompaniesToFinance extends Command
{
    protected $signature = 'licence:sync-companies {--dry-run : count only}';

    protected $description = 'Tell Dot.Finance about every existing company';

    public function handle(FinanceCompanies $companies, FinanceEntitlements $entitlements): int
    {
        if (! $entitlements->configured()) {
            $this->warn('The Finance link is not switched on (LICENCE_FINANCE_CONNECTED, LICENCE_FINANCE_URL, FINANCE_ENTITLEMENT_SECRET); nothing sent.');

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
