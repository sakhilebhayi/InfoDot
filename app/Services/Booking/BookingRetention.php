<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Services\Privacy\BookingDataEraser;

/**
 * Removes the personal details from appointments that ended a long time ago (R-63 phase 7, POPIA). The business keeps the appointment itself (when,
 * what, with whom on its side, how it ended) for its figures, with nothing that says who the customer was. Same erasure an account deletion does.
 */
class BookingRetention
{
    /** @return int how many appointments were cleaned */
    public function run(?int $months = null): int
    {
        $months ??= (int) config('booking.retention_months');
        $cleaned = 0;

        Booking::query()->where('ends_at', '<', now()->subMonthsNoOverflow($months))->where('customer_email', '!=', BookingDataEraser::EMAIL)
            ->chunkById(200, function ($bookings) use (&$cleaned) {
                foreach ($bookings as $booking) {
                    app(BookingDataEraser::class)->scrub($booking, true);
                    $cleaned++;
                }
            });

        return $cleaned;
    }
}
