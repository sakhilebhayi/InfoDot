<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingRetention;
use Illuminate\Console\Command;

class ApplyBookingRetention extends Command
{
    protected $signature = 'booking:retention';

    protected $description = 'Remove the personal details from appointments that ended long ago (R-63, POPIA)';

    public function handle(BookingRetention $retention): int
    {
        $this->info('Cleaned: '.$retention->run());

        return self::SUCCESS;
    }
}
