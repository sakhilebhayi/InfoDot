<?php

namespace App\Console\Commands;

use App\Services\Seminars\SeminarService;
use Illuminate\Console\Command;

/** Releases places held for payment that ran out of time, and asks again for refunds that were requested but not confirmed. */
class SweepSeminarPayments extends Command
{
    protected $signature = 'seminars:sweep-payments';

    protected $description = 'Release expired ticket holds and retry unconfirmed refunds';

    public function handle(SeminarService $seminars): int
    {
        $this->info('Released '.$seminars->expireHolds().' hold(s); retried '.$seminars->retryRefunds().' refund(s).');

        return self::SUCCESS;
    }
}
