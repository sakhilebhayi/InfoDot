<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * What a business does with a booking (R-63 phase 4): accept or reject a request, cancel, move to another time, hand to another provider, mark an
 * appointment done or missed, and keep internal notes.
 *
 * Who: the owner, administrators and managers act on every booking of the business; a member who is a provider acts on the bookings assigned to them
 * and cannot hand them to someone else. Checked here every time, so no screen can forget it.
 *
 * How: every action takes the SAME locks in the SAME order as a customer making a booking (the business's settings row, then the booking row), so a
 * move or an assignment and a new booking for the same time cannot both succeed and two business actions cannot cross. The new time of a move or
 * assignment is checked again by the availability engine under the lock, ignoring the booking itself and the least-notice rule (that rule protects the
 * business from customers, not from its own decisions). Nothing here writes a customer's details to the audit trail.
 *
 * Status moves: pending to confirmed, rejected or cancelled; confirmed to cancelled, completed or no_show (only once it has started); a move keeps
 * the status. Rejected, cancelled, completed and no_show are final.
 */
class BookingActions
{
    public function __construct(private readonly Availability $availability) {}

    /** Whether this person may act on this booking: a manager of the business, or the provider it is assigned to. */
    public static function canManage(User $user, Booking $booking): bool
    {
        if (Gate::forUser($user)->allows('manageBookings', $booking->team)) {
            return true;
        }

        return $booking->booking_provider_id !== null
            && BookingProvider::query()->whereKey($booking->booking_provider_id)->where('team_id', $booking->team_id)->where('user_id', $user->getKey())->exists();
    }

    /** @throws BookingRefused */
    public function accept(Booking $booking, User $actor): Booking
    {
        return $this->act($booking, $actor, 'booking_accepted', function (Booking $b) {
            $this->mustBe($b, [Booking::PENDING]);
            $this->mustNotHaveStarted($b);
            $b->forceFill(['status' => Booking::CONFIRMED, 'status_note' => null])->save();

            return [];
        });
    }

    /** @throws BookingRefused */
    public function reject(Booking $booking, User $actor, ?string $note = null): Booking
    {
        return $this->act($booking, $actor, 'booking_rejected', function (Booking $b) use ($note) {
            $this->mustBe($b, [Booking::PENDING]);
            $b->forceFill(['status' => Booking::REJECTED, 'status_note' => $this->trimNote($note), 'reschedule_requested_at' => null, 'reschedule_note' => null])->save();

            return [];
        });
    }

    /** @throws BookingRefused */
    public function cancel(Booking $booking, User $actor, ?string $note = null): Booking
    {
        return $this->act($booking, $actor, 'booking_cancelled_by_business', function (Booking $b) use ($note) {
            $this->mustBe($b, Booking::HOLDING);
            $this->mustNotHaveStarted($b);
            $b->forceFill(['status' => Booking::CANCELLED_BY_BUSINESS, 'cancelled_at' => now(), 'status_note' => $this->trimNote($note), 'reschedule_requested_at' => null, 'reschedule_note' => null])->save();

            return [];
        });
    }

    /** An appointment that took place. @throws BookingRefused */
    public function complete(Booking $booking, User $actor): Booking
    {
        return $this->finish($booking, $actor, Booking::COMPLETED, 'booking_completed');
    }

    /** An appointment the customer did not come to. @throws BookingRefused */
    public function noShow(Booking $booking, User $actor): Booking
    {
        return $this->finish($booking, $actor, Booking::NO_SHOW, 'booking_no_show');
    }

    /**
     * Move the appointment to another start time (and keep its provider, or the pool when it has none). The new time must be bookable for that provider
     * as things stand now, ignoring this booking itself. A reschedule request from the customer is answered by this.
     *
     * @throws BookingRefused
     */
    public function reschedule(Booking $booking, User $actor, CarbonInterface $newStart): Booking
    {
        return $this->act($booking, $actor, 'booking_rescheduled', function (Booking $b) use ($newStart) {
            $this->mustBe($b, Booking::HOLDING);
            $this->mustNotHaveStarted($b);

            $type = $b->appointmentType()->where('team_id', $b->team_id)->firstOrFail();
            $slot = $this->availability->find($type, $newStart, $b->booking_provider_id, now(), $b->getKey(), true, true);

            if ($slot === null) {
                throw new BookingRefused(BookingRefused::SLOT_TAKEN, 'That time is not free for this appointment. Choose another time.');
            }

            $from = $b->starts_at->toIso8601String();
            $b->forceFill([
                'starts_at' => $slot->start, 'ends_at' => $slot->end, 'booking_provider_id' => $slot->providerId,
                'blocks_from' => $slot->start->subMinutes($type->buffer_before_minutes), 'blocks_until' => $slot->end->addMinutes($type->buffer_after_minutes),
                'reschedule_requested_at' => null, 'reschedule_note' => null,
            ])->save();

            return ['from' => $from, 'to' => $slot->start->toIso8601String()];
        });
    }

    /** Hand the appointment to another provider (null = whoever of the pool is free). Managers only. @throws BookingRefused */
    public function assign(Booking $booking, User $actor, ?int $providerId): Booking
    {
        return $this->act($booking, $actor, 'booking_provider_assigned', function (Booking $b) use ($actor, $providerId) {
            if (! Gate::forUser($actor)->allows('manageBookings', $b->team)) {
                throw new BookingRefused(BookingRefused::NOT_ALLOWED, 'Only the people who run the business can hand an appointment to someone else.');
            }

            $this->mustBe($b, Booking::HOLDING);
            $this->mustNotHaveStarted($b);

            $type = $b->appointmentType()->where('team_id', $b->team_id)->firstOrFail();
            $slot = $this->availability->find($type, $b->starts_at, $providerId, now(), $b->getKey(), true, true);

            if ($slot === null) {
                throw new BookingRefused(BookingRefused::SLOT_TAKEN, 'That person is not free then, or does not offer this appointment.');
            }

            $from = $b->booking_provider_id;
            $b->forceFill(['booking_provider_id' => $slot->providerId])->save();

            return ['from_provider' => $from, 'to_provider' => $slot->providerId];
        });
    }

    /** The business's private notes on a booking; the customer never sees them. Allowed in any status. @throws BookingRefused */
    public function note(Booking $booking, User $actor, ?string $text): Booking
    {
        return $this->act($booking, $actor, 'booking_note_updated', function (Booking $b) use ($text) {
            $b->forceFill(['internal_notes' => filled($text) ? mb_substr(trim($text), 0, 2000) : null])->save();

            return [];
        });
    }

    /** Say no to a customer's request to move, leaving the appointment as it is. @throws BookingRefused */
    public function declineReschedule(Booking $booking, User $actor, ?string $note = null): Booking
    {
        return $this->act($booking, $actor, 'booking_reschedule_declined', function (Booking $b) use ($note) {
            if ($b->reschedule_requested_at === null) {
                throw new BookingRefused(BookingRefused::STATE, 'There is no request to move this appointment.');
            }

            $b->forceFill(['reschedule_requested_at' => null, 'reschedule_note' => null, 'status_note' => $this->trimNote($note)])->save();

            return [];
        });
    }

    // ------------------------------------------------------------------------------------------------------------------------------ internals

    /**
     * The one path every action goes through: a transaction that takes the business's settings row and then the booking row (the order a customer's
     * booking uses), checks the person's right to act on the fresh row, runs the change and writes the audit entry.
     *
     * @param  Closure(Booking): array<string, mixed>  $change  returns the extra details for the audit entry
     */
    private function act(Booking $booking, User $actor, string $event, Closure $change): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $event, $change) {
            BookingSetting::query()->where('team_id', $booking->team_id)->lockForUpdate()->first();
            $fresh = Booking::query()->whereKey($booking->getKey())->where('team_id', $booking->team_id)->lockForUpdate()->firstOrFail();

            if (! self::canManage($actor, $fresh)) {
                throw new BookingRefused(BookingRefused::NOT_ALLOWED, 'You cannot act on this appointment.');
            }

            $details = $change($fresh);

            BookingEvent::query()->create(['team_id' => $fresh->team_id, 'actor_id' => $actor->getKey(), 'type' => $event, 'details' => ['booking_id' => $fresh->getKey()] + $details]);

            return $fresh;
        }, 3);
    }

    private function finish(Booking $booking, User $actor, string $status, string $event): Booking
    {
        return $this->act($booking, $actor, $event, function (Booking $b) use ($status) {
            $this->mustBe($b, [Booking::CONFIRMED]);

            if ($b->starts_at->isFuture()) {
                throw new BookingRefused(BookingRefused::STATE, 'This appointment has not started yet.');
            }

            $b->forceFill(['status' => $status])->save();

            return [];
        });
    }

    /** @param  list<string>  $statuses */
    private function mustBe(Booking $b, array $statuses): void
    {
        if (! in_array($b->status, $statuses, true)) {
            throw new BookingRefused(BookingRefused::STATE, 'This appointment is '.str_replace('_', ' ', $b->status).', so that cannot be done now.');
        }
    }

    private function mustNotHaveStarted(Booking $b): void
    {
        if ($b->starts_at->lte(CarbonImmutable::now())) {
            throw new BookingRefused(BookingRefused::TOO_LATE, 'This appointment has already started.');
        }
    }

    private function trimNote(?string $text): ?string
    {
        return filled($text) ? mb_substr(trim($text), 0, 300) : null;
    }
}
