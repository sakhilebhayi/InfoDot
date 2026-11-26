<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingActions;

/**
 * R-72 (Q-21): who may act on one appointment for the business: anyone who manages the business's bookings, or the provider it is with
 * while they are still in the business and still an active provider (BookingActions::canManage holds the rule).
 */
class BookingPolicy
{
    public function manage(User $user, Booking $booking): bool
    {
        return BookingActions::canManage($user, $booking);
    }
}
