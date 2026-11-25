<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingExpiry;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'booking:expire-pending';

    protected $description = 'Let go of appointment requests nobody answered, so they stop holding the time (R-63)';

    public function handle(BookingExpiry $expiry): int
    {
        $this->info('Expired: '.$expiry->run());

        return self::SUCCESS;
    }
}
