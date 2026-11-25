<?php

namespace App\Services\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Making and withdrawing a booking (R-63 phase 3): the one place a customer's booking is created, so every rule is applied once.
 *
 * Creating a booking takes a row lock on the business's booking settings, so two people reaching for the same time are served one after the other
 * and the second finds the time taken. Inside that lock the time is checked again by the availability engine (hours, closures, notice, horizon,
 * buffers, other bookings), which is what makes "two customers reserving the same slot" impossible; what the person saw on the page is never trusted.
 * The lock serialises bookings of one business, which is cheap for a business that takes a handful an hour and avoids per-provider lock ordering.
 *
 * A pending booking holds its time, so one person's holds are capped (config booking.limits). Nothing here stores or logs a customer's details in the
 * audit trail.
 */
class Bookings
{
    public function __construct(private readonly Availability $availability) {}

    /**
     * Why this person cannot book this business right now, or null when they can. Cheap, for the page; `book()` checks it all again under the lock.
     */
    public function whyNot(?User $user, Team $team): ?string
    {
        if (! app(BookingFeature::class)->available()) {
            return 'Appointment booking is not open yet.';
        }

        if ($team->personal_team || $team->isRestricted()) {
            return $team->name.' is not taking appointments through InfoDot right now.';
        }

        $setting = BookingSetting::forTeam($team);

        if (! $setting->isOpen() || ! $setting->calendar_published) {
            return $team->name.' is not taking appointments through InfoDot right now.';
        }

        if ($user !== null && ($user->belongsToTeam($team) || $user->ownsTeam($team))) {
            return 'You belong to '.$team->name.', so you manage its appointments instead of booking them.';
        }

        return null;
    }

    /**
     * Make a booking. The details are already validated; nothing is trusted from the page except which type and which start the person asked for.
     *
     * @param  array{name: string, email: string, phone?: ?string, company?: ?string, reason?: ?string, terms_accepted?: bool}  $details
     *
     * @throws BookingRefused
     */
    public function book(User $customer, Team $team, AppointmentType $type, CarbonInterface $start, ?int $providerId, array $details): Booking
    {
        if (($why = $this->whyNot($customer, $team)) !== null) {
            throw new BookingRefused(BookingRefused::CLOSED, $why);
        }

        if ((int) $type->team_id !== (int) $team->getKey()) {
            throw new BookingRefused(BookingRefused::SLOT_TAKEN, 'That appointment is not offered any more. Choose another.');
        }

        return DB::transaction(function () use ($customer, $team, $type, $start, $providerId, $details) {
            // The mutex for this business: everyone booking it queues here.
            $setting = BookingSetting::query()->where('team_id', $team->getKey())->lockForUpdate()->first();

            if ($setting === null || ! $setting->isOpen() || ! $setting->calendar_published) {
                throw new BookingRefused(BookingRefused::CLOSED, $team->name.' is not taking appointments through InfoDot right now.');
            }

            // The caps are counted under the lock too, so two requests from one account at the same moment cannot both slip under them.
            $this->withinLimits($customer, $team, $type, $start);

            // The type is read again under the lock: one switched off since the page was drawn is no longer offered.
            $type = AppointmentType::query()->whereKey($type->getKey())->where('team_id', $team->getKey())->where('active', true)->first();

            if ($type === null) {
                throw new BookingRefused(BookingRefused::SLOT_TAKEN, 'That appointment is not offered any more. Choose another.');
            }

            $slot = $this->availability->find($type, $start, $providerId, now(), null, true);

            if ($slot === null) {
                throw new BookingRefused(BookingRefused::SLOT_TAKEN, 'That time has just been taken or is no longer available. Choose another time.');
            }

            $booking = Booking::query()->create([
                'team_id' => $team->getKey(),
                'appointment_type_id' => $type->getKey(),
                'booking_provider_id' => $slot->providerId,
                'customer_user_id' => $customer->getKey(),
                'customer_name' => $details['name'],
                'customer_email' => $details['email'],
                'customer_phone' => $details['phone'] ?? null,
                'customer_company' => $details['company'] ?? null,
                'reason' => $details['reason'] ?? null,
                'starts_at' => $slot->start,
                'ends_at' => $slot->end,
                'blocks_from' => $slot->start->subMinutes($type->buffer_before_minutes),
                'blocks_until' => $slot->end->addMinutes($type->buffer_after_minutes),
                'status' => $type->needsApproval($setting) ? Booking::PENDING : Booking::CONFIRMED,
                'terms_accepted_at' => ($details['terms_accepted'] ?? false) ? now() : null,
            ]);

            $booking->forceFill(['reference' => $this->reference($team, $booking)])->save();

            BookingEvent::query()->create(['team_id' => $team->getKey(), 'actor_id' => $customer->getKey(), 'type' => 'booking_created', 'details' => [
                'booking_id' => $booking->getKey(), 'appointment_type_id' => $type->getKey(), 'provider_id' => $slot->providerId, 'status' => $booking->status,
                'starts_at' => $slot->start->toIso8601String(),
            ]]);

            return $booking;
        }, 3); // a deadlock or a lock wait is tried again, twice, instead of failing the person with an error
    }

    /** Whether this person may withdraw this booking themselves right now, and if not, why. */
    public function whyNotCancel(Booking $booking, User $actor): ?string
    {
        if ($booking->customer_user_id === null || (int) $booking->customer_user_id !== (int) $actor->getKey()) {
            return 'This appointment is not yours.';
        }

        if (! in_array($booking->status, Booking::HOLDING, true)) {
            return 'This appointment is already closed.';
        }

        if ($booking->starts_at->lte(now())) {
            return 'This appointment has already started.';
        }

        $setting = BookingSetting::forTeam($booking->team);

        // A request still waiting for the business may be withdrawn at any time; a confirmed appointment only until the business's cut-off.
        if ($booking->status === Booking::CONFIRMED && now()->gte($booking->starts_at->copy()->subHours($setting->cancel_cutoff_hours))) {
            return 'It is too close to the appointment to cancel it here. Please contact '.$booking->team->name.'.';
        }

        return null;
    }

    /** @throws BookingRefused */
    public function cancelByCustomer(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (($why = $this->whyNotCancel($fresh, $actor)) !== null) {
                throw new BookingRefused($fresh->customer_user_id !== null && (int) $fresh->customer_user_id === (int) $actor->getKey() ? BookingRefused::TOO_LATE : BookingRefused::NOT_YOURS, $why);
            }

            $fresh->forceFill(['status' => Booking::CANCELLED_BY_CUSTOMER, 'cancelled_at' => now(), 'reschedule_requested_at' => null, 'reschedule_note' => null])->save();

            BookingEvent::query()->create(['team_id' => $fresh->team_id, 'actor_id' => $actor->getKey(), 'type' => 'booking_cancelled_by_customer', 'details' => ['booking_id' => $fresh->getKey()]]);

            return $fresh;
        });
    }

    /** Whether this person may ask to move this appointment, and if not, why. The same cut-off as cancelling applies to a confirmed one. */
    public function whyNotReschedule(Booking $booking, User $actor): ?string
    {
        if (($why = $this->whyNotCancel($booking, $actor)) !== null) {
            return str_contains($why, 'too close to the appointment to cancel') ? 'It is too close to the appointment to move it here. Please contact '.$booking->team->name.'.' : $why;
        }

        return $booking->reschedule_requested_at !== null ? 'You have already asked '.$booking->team->name.' to move this appointment. They will answer on this page.' : null;
    }

    /**
     * The customer asks the business to move the appointment. Nothing moves: the booking keeps its time until the business moves it or says no.
     *
     * @throws BookingRefused
     */
    public function requestReschedule(Booking $booking, User $actor, string $note): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $note) {
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (($why = $this->whyNotReschedule($fresh, $actor)) !== null) {
                throw new BookingRefused($fresh->customer_user_id !== null && (int) $fresh->customer_user_id === (int) $actor->getKey() ? BookingRefused::TOO_LATE : BookingRefused::NOT_YOURS, $why);
            }

            $fresh->forceFill(['reschedule_requested_at' => now(), 'reschedule_note' => mb_substr(trim($note), 0, 300)])->save();

            BookingEvent::query()->create(['team_id' => $fresh->team_id, 'actor_id' => $actor->getKey(), 'type' => 'booking_reschedule_requested', 'details' => ['booking_id' => $fresh->getKey()]]);

            return $fresh;
        });
    }

    /**
     * The reference a customer quotes: the business's owner code, B for booking, and ten random characters. Random on purpose: a number counted up from
     * one would tell every customer and every business how many bookings the whole platform has taken.
     */
    public function reference(Team $team, Booking $booking): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0 O 1 I L

        do {
            $suffix = '';
            for ($i = 0; $i < 10; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = ($team->code ?? 'INFO').'-B-'.$suffix;
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @throws BookingRefused */
    private function withinLimits(User $customer, Team $team, AppointmentType $type, CarbonInterface $start): void
    {
        $limits = (array) config('booking.limits');
        $since = CarbonImmutable::now()->subDay();

        $holding = Booking::query()->where('customer_user_id', $customer->getKey())->holding()->where('starts_at', '>', now());

        if ((clone $holding)->where('team_id', $team->getKey())->count() >= (int) $limits['active_per_business']) {
            throw new BookingRefused(BookingRefused::LIMIT, 'You already hold '.$limits['active_per_business'].' appointments with '.$team->name.'. Cancel one or wait until one has taken place.');
        }

        if ((clone $holding)->where('team_id', $team->getKey())->where('appointment_type_id', $type->getKey())->where('starts_at', CarbonImmutable::instance($start)->utc())->exists()) {
            throw new BookingRefused(BookingRefused::LIMIT, 'You have already booked that time.');
        }

        $made = Booking::query()->where('customer_user_id', $customer->getKey())->where('created_at', '>=', $since);

        if ((clone $made)->where('team_id', $team->getKey())->count() >= (int) $limits['new_per_business_day']) {
            throw new BookingRefused(BookingRefused::LIMIT, 'You have made '.$limits['new_per_business_day'].' bookings with '.$team->name.' today, which is the most InfoDot allows. Try again tomorrow.');
        }

        if ($made->count() >= (int) $limits['new_per_day']) {
            throw new BookingRefused(BookingRefused::LIMIT, 'You have made '.$limits['new_per_day'].' bookings today, which is the most InfoDot allows. Try again tomorrow.');
        }
    }
}
