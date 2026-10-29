<?php

namespace App\Console\Commands;

use App\Services\Seminars\CommissionInvoices;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Issues last month's commission invoices (or credit notes) to hosts. Nothing is issued until InfoDot's own details are configured. */
class IssueSeminarCommissionInvoices extends Command
{
    protected $signature = 'seminars:invoice-commission {--month= : YYYY-MM, default last month}';

    protected $description = 'Issue monthly commission invoices to seminar hosts';

    public function handle(CommissionInvoices $invoices): int
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', (string) $this->option('month'), config('app.timezone')) : now(config('app.timezone'))->subMonthNoOverflow();

        if (! $invoices->configured()) {
            $this->warn('InfoDot issuer details (SEMINARS_ISSUER_NAME, SEMINARS_ISSUER_ADDRESS, SEMINARS_ISSUER_VAT_NUMBER) are not set; nothing issued.');

            return self::SUCCESS;
        }

        $this->info('Issued '.$invoices->issueMonth($month).' invoice(s) for '.$month->format('Y-m').'.');

        return self::SUCCESS;
    }
}
