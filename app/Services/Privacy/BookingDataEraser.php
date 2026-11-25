<?php

namespace App\Services\Privacy;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\User;

/**
 * What deleting an InfoDot account does to the appointments the person booked (R-63). Their upcoming pending and confirmed bookings are cancelled
 * so they stop holding a business's time, and every booking they ever made loses the personal details on it (name, email, phone, company, reason and
 * answers). The business keeps the appointment itself, with nothing that says who it was: it is part of the business's own records.
 * Runs inside the account-deletion transaction.
 */
class BookingDataEraser
{
    public const NAME = '[deleted account]';

    public const EMAIL = 'deleted@invalid';

    public function erase(User $user): void
    {
        $mine = Booking::query()->where('customer_user_id', $user->getKey())->lockForUpdate()->get();

        foreach ($mine as $booking) {
            if (in_array($booking->status, Booking::HOLDING, true) && $booking->starts_at->isFuture()) {
                $booking->forceFill(['status' => Booking::CANCELLED_BY_CUSTOMER, 'cancelled_at' => now()]);

                BookingEvent::query()->create(['team_id' => $booking->team_id, 'actor_id' => null, 'type' => 'booking_cancelled_account_deleted', 'details' => ['booking_id' => $booking->getKey()]]);
            }

            $booking->forceFill(['customer_name' => self::NAME, 'customer_email' => self::EMAIL, 'customer_phone' => null, 'customer_company' => null, 'reason' => null, 'answers' => null])->save();
        }
    }
}
