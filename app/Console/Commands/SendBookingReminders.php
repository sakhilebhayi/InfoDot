<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Booking\BookingNotifier;
use Illuminate\Console\Command;

/**
 * Reminds both sides of a confirmed appointment (R-63). A reminder is due `hours` before the start and is sent in the hour after it falls due, once (the
 * notifier makes it one notice per person and lead time, and mails only when the notice is new). A booking made after a reminder's lead time had already
 * passed gets none for it, so nobody is reminded of something they booked a minute ago.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'booking:remind';

    protected $description = 'Send appointment reminders that have fallen due (R-63)';

    /** How long after it falls due a reminder may still be sent, in minutes. A reminder missed for longer than this is skipped, not sent late. */
    private const GRACE_MINUTES = 60;

    public function handle(BookingNotifier $notifier): int
    {
        $lead = ['customer' => (array) config('booking.reminders.customer_hours'), 'staff' => (array) config('booking.reminders.staff_hours')];
        $longest = max(array_merge($lead['customer'], $lead['staff'], [1]));
        $sent = 0;

        Booking::query()->where('status', Booking::CONFIRMED)->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours($longest))
            ->with(['team', 'appointmentType'])->orderBy('id')->chunkById(100, function ($bookings) use ($lead, $notifier, &$sent) {
                foreach ($bookings as $booking) {
                    foreach ($lead as $side => $hours) {
                        foreach ($hours as $h) {
                            $due = $booking->starts_at->copy()->subHours((int) $h);

                            if (now()->gte($due) && now()->lt($due->copy()->addMinutes(self::GRACE_MINUTES)) && $booking->created_at->lte($due)) {
                                $sent += $notifier->reminder($booking, $side, (int) $h) ? 1 : 0;
                            }
                        }
                    }
                }
            });

        $this->info('Reminders sent: '.$sent);

        return self::SUCCESS;
    }
}
