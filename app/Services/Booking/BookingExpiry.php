<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use Illuminate\Support\Facades\DB;

/**
 * Lets go of requests nobody answered (R-63 phase 5), so a pending booking cannot hold a business's time for ever. A request expires when it has waited
 * `booking.pending_hours` without an answer, or when its start time arrives unanswered. It takes the same locks in the same order as every other change
 * (the business's settings row, then the booking row), decides again from the fresh row, writes one audit entry and tells the customer.
 */
class BookingExpiry
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /** @return int how many requests expired */
    public function run(): int
    {
        $cutoff = now()->subHours((int) config('booking.pending_hours'));
        $count = 0;

        Booking::query()->where('status', Booking::PENDING)->where(fn ($q) => $q->where('created_at', '<=', $cutoff)->orWhere('starts_at', '<=', now()))
            ->orderBy('id')->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    $count += $this->expire($booking) ? 1 : 0;
                }
            });

        return $count;
    }

    private function expire(Booking $booking): bool
    {
        $done = DB::transaction(function () use ($booking) {
            BookingSetting::query()->where('team_id', $booking->team_id)->lockForUpdate()->first();
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== Booking::PENDING) {
                return null; // answered meanwhile
            }

            $fresh->forceFill(['status' => Booking::EXPIRED, 'status_note' => 'There was no answer in time.', 'reschedule_requested_at' => null, 'reschedule_note' => null])->save();
            BookingEvent::query()->create(['team_id' => $fresh->team_id, 'actor_id' => null, 'type' => 'booking_expired', 'details' => ['booking_id' => $fresh->getKey()]]);

            return $fresh;
        }, 3);

        if ($done === null) {
            return false;
        }

        $this->notifier->expired($done);

        return true;
    }
}
