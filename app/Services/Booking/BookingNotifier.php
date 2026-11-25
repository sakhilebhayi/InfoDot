<?php

namespace App\Services\Booking;

use App\Mail\BookingActivity;
use App\Models\Booking;
use App\Models\BookingProvider;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the people an appointment concerns what happened to it (R-63 phase 5): a bell notice on InfoDot and an email, once per fact.
 *
 * Who: the customer (the account that booked, never the address typed into the form) and, for the business, the provider the booking is assigned to,
 * or the owner, administrators and managers when it has none. Nobody is told about their own action. A person with no verified email gets the bell only.
 * What: only that something happened, to which appointment, when and with whom; no customer contact details, reason or private notes ever go in a notice
 * or an email. Each fact is one notice (unique per person, kind and reference), and the email is sent only when the notice is new, so a retry or a second
 * call never sends twice. Called after the change is saved and never able to fail it: a mail outage is reported and the bell still has the notice.
 */
class BookingNotifier
{
    public function __construct(private readonly InAppNotices $notices) {}

    /** A new booking: the customer is told it was sent (or confirmed straight away), and the business that it arrived. */
    public function submitted(Booking $booking): void
    {
        $this->safely(function () use ($booking) {
            $customer = $this->customer($booking);
            // The customer is told what became of their own request, so no actor is named for them; the business is told it was the customer.
            $this->tell($customer, $booking, $booking->status === Booking::CONFIRMED ? UserNotice::BOOKING_CONFIRMED : UserNotice::BOOKING_SUBMITTED, 'created', null);

            foreach ($this->staff($booking) as $person) {
                $this->tell($person, $booking, UserNotice::BOOKING_RECEIVED, 'created', $customer);
            }
        });
    }

    public function confirmed(Booking $booking, User $actor): void
    {
        $this->safely(fn () => $this->tell($this->customer($booking), $booking, UserNotice::BOOKING_CONFIRMED, 'accepted', $actor));
    }

    public function rejected(Booking $booking, User $actor): void
    {
        $this->safely(fn () => $this->tell($this->customer($booking), $booking, UserNotice::BOOKING_REJECTED, 'rejected', $actor));
    }

    public function cancelledByBusiness(Booking $booking, User $actor): void
    {
        $this->safely(fn () => $this->tell($this->customer($booking), $booking, UserNotice::BOOKING_CANCELLED, 'cancelled', $actor));
    }

    public function cancelledByCustomer(Booking $booking, User $actor): void
    {
        $this->safely(function () use ($booking, $actor) {
            foreach ($this->staff($booking) as $person) {
                $this->tell($person, $booking, UserNotice::BOOKING_CANCELLED, 'cancelled', $actor);
            }
        });
    }

    public function rescheduled(Booking $booking, User $actor): void
    {
        $this->safely(fn () => $this->tell($this->customer($booking), $booking, UserNotice::BOOKING_RESCHEDULED, 'moved:'.now()->getTimestamp().':'.$booking->starts_at->getTimestamp(), $actor));
    }

    public function rescheduleRequested(Booking $booking, User $actor): void
    {
        $this->safely(function () use ($booking, $actor) {
            foreach ($this->staff($booking) as $person) {
                $this->tell($person, $booking, UserNotice::BOOKING_RESCHEDULE_REQUESTED, 'asked:'.($booking->reschedule_requested_at?->getTimestamp() ?? 0), $actor);
            }
        });
    }

    public function rescheduleDeclined(Booking $booking, User $actor): void
    {
        $this->safely(fn () => $this->tell($this->customer($booking), $booking, UserNotice::BOOKING_RESCHEDULE_DECLINED, 'kept:'.now()->getTimestamp(), $actor));
    }

    /** The provider an appointment was handed to is told it is theirs now. */
    public function assigned(Booking $booking, User $actor): void
    {
        $this->safely(function () use ($booking, $actor) {
            foreach ($this->staff($booking) as $person) {
                $this->tell($person, $booking, UserNotice::BOOKING_RECEIVED, 'assigned:'.($booking->booking_provider_id ?? 0).':'.now()->getTimestamp(), $actor);
            }
        });
    }

    /** A request nobody answered was let go. */
    public function expired(Booking $booking): void
    {
        $this->safely(fn () => $this->tell($this->customer($booking), $booking, UserNotice::BOOKING_EXPIRED, 'expired', null));
    }

    /**
     * A reminder for one side, once per lead time. Returns whether one was sent now (false: already sent, or nobody to tell).
     *
     * @param  'customer'|'staff'  $side
     */
    public function reminder(Booking $booking, string $side, int $hours): bool
    {
        $sent = false;

        $this->safely(function () use ($booking, $side, $hours, &$sent) {
            $people = $side === 'customer' ? array_filter([$this->customer($booking)]) : $this->staff($booking);

            foreach ($people as $person) {
                $sent = $this->tell($person, $booking, UserNotice::BOOKING_REMINDER, 'remind:'.$hours.':'.$booking->starts_at->getTimestamp(), null, $hours) || $sent;
            }
        });

        return $sent;
    }

    // ------------------------------------------------------------------------------------------------------------------------------ internals

    /** One notice and, when it is new and the person has a verified email, one email. */
    private function tell(?User $recipient, Booking $booking, string $kind, string $ref, ?User $actor, ?int $hours = null): bool
    {
        if ($recipient === null) {
            return false;
        }

        if (! $this->notices->record($kind, $recipient, 'booking', $booking->getKey(), 'bk:'.$booking->getKey().':'.$ref, $actor)) {
            return false; // already told, told them about their own act, or they no longer exist
        }

        if ($recipient->email_verified_at === null) {
            return true;
        }

        // The person's choice for bookings (R-64 phase 8): email as it happens, in the daily summary, or none. The bell has it either way.
        if (! app(NotificationPreferences::class)->instantEmail($recipient, $kind)) {
            return true;
        }

        // A ceiling on booking mail to one person in a day, so nobody can be buried in mail by many accounts: past it, the bell still tells them.
        $today = 'booking-mail:'.$recipient->getKey().':'.now()->format('Y-m-d');
        Cache::add($today, 0, now()->addDay());

        if (Cache::increment($today) > (int) config('booking.mail_per_person_per_day')) {
            return true;
        }

        $toCustomer = $booking->customer_user_id !== null && (int) $booking->customer_user_id === (int) $recipient->getKey();

        try {
            Mail::to($recipient)->send(new BookingActivity($kind, $booking, $recipient, $toCustomer, $hours));
        } catch (\Throwable $e) {
            // The notice is on the bell; a mail outage must not undo or fail what happened to the appointment. Only the class is logged: a transport
            // error can carry the recipient's address.
            Log::error('booking.mail_failed', ['booking_id' => $booking->getKey(), 'kind' => $kind, 'exception' => $e::class]);
        }

        return true;
    }

    private function customer(Booking $booking): ?User
    {
        return $booking->customer_user_id === null ? null : User::query()->find($booking->customer_user_id);
    }

    /**
     * The people on the business side: the assigned provider while they are still in the business and active, otherwise the owner, administrators and managers.
     *
     * @return list<User>
     */
    private function staff(Booking $booking): array
    {
        $team = $booking->team;

        if ($booking->booking_provider_id !== null) {
            $provider = BookingProvider::query()->where('team_id', $booking->team_id)->whereKey($booking->booking_provider_id)->where('active', true)->first();
            $person = $provider === null ? null : User::query()->find($provider->user_id);

            if ($person !== null && $person->belongsToTeam($team)) {
                return [$person];
            }
        }

        $people = [];

        foreach ([$team->owner, ...$team->users()->wherePivotIn('role', ['admin', 'manager'])->get()->all()] as $user) {
            if ($user instanceof User) {
                $people[$user->getKey()] = $user;
            }
        }

        return array_values($people);
    }

    private function safely(callable $do): void
    {
        try {
            $do();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
