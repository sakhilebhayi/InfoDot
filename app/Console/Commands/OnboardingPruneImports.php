<?php

namespace App\Console\Commands;

use App\Models\CompanyProfileImport;
use Illuminate\Console\Command;

/**
 * R-62 phase 2: a company import that was never confirmed is deleted once its retention has passed, with everything kept in it (the values a
 * source offered, and later the reference to an uploaded document). A confirmed import is kept as the record of where a company's details came
 * from. Safe to run any number of times.
 */
class OnboardingPruneImports extends Command
{
    protected $signature = 'onboarding:prune-imports';

    protected $description = 'Delete company imports that were never confirmed and have passed their retention';

    public function handle(): int
    {
        $n = CompanyProfileImport::query()->where('status', '!=', CompanyProfileImport::CONFIRMED)->where('expires_at', '<', now())->delete();

        $this->info($n.' unconfirmed '.($n === 1 ? 'import' : 'imports').' deleted.');

        return self::SUCCESS;
    }
}
