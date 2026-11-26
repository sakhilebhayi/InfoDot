<?php

namespace App\Services\Enquiries;

use App\Models\Enquiry;
use App\Models\Team;
use App\Models\User;
use App\Services\Booking\BookingFeature;
use App\Services\Booking\Bookings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * R-66 phase 3: "Request an appointment". A business that has not published a booking calendar can
 * still be asked for a meeting. The request is an enquiry in the `appointment` category, so it lands
 * in the business inbox under the same limits, blocks and notices as any message. The business
 * accepts it with a date and time, or declines it with a reason; the answer is written into the
 * conversation, which tells the requester through the usual enquiry notice and email. An accepted
 * request is recorded on the enquiry only: it does not reserve calendar time, because there is no calendar.
 */
final class AppointmentRequests
{
    public function __construct(
        private readonly EnquiryService $enquiries,
        private readonly Bookings $bookings,
        private readonly BookingFeature $feature,
    ) {}

    /** Whether the business page offers "Request an appointment" to people outside the business. */
    public function offered(Team $team): bool
    {
        return $this->feature->available()
            && $team->acceptsEnquiries()
            && ! $this->bookable($team);
    }

    /** Why this person cannot send a request to this business, or null when they can. */
    public function whyNot(User $user, Team $team): ?string
    {
        if (! $this->feature->available()) {
            return 'Appointments are not open on InfoDot yet.';
        }

        if ($this->bookable($team) && ! $user->belongsToTeam($team)) {
            return $team->name.' has a booking calendar. Choose a time on it instead.';
        }

        return $this->enquiries->whyNot($user, $team);
    }

    /**
     * Send the request. The fields are already validated by the controller.
     *
     * @param  array{on: string, time: string, minutes: int, about: string, company_team?: ?Team}  $data
     */
    public function request(User $user, Team $team, array $data): Enquiry
    {
        if ($reason = $this->whyNot($user, $team)) {
            throw ValidationException::withMessages(['about' => $reason]);
        }

        $on = Carbon::parse($data['on']);

        return $this->enquiries->start($user, $team, [
            'subject' => 'Appointment request for '.$on->format('j M Y'),
            'category' => Enquiry::APPOINTMENT,
            'body' => $data['about'],
            'company_team' => $data['company_team'] ?? null,
            'appointment' => ['on' => $on->toDateString(), 'time' => $data['time'], 'minutes' => $data['minutes']],
        ]);
    }

    /** The business agrees a date and time. The requester is told through the conversation. */
    public function accept(Enquiry $enquiry, User $actor, Carbon $at, int $minutes, ?string $note = null): void
    {
        $this->guardOpen($enquiry);

        if ($at->isPast()) {
            throw ValidationException::withMessages(['at' => 'Choose a time that has not passed yet.']);
        }

        DB::transaction(function () use ($enquiry, $actor, $at, $minutes, $note) {
            $enquiry->appointment_state = Enquiry::APPOINTMENT_ACCEPTED;
            $enquiry->appointment_at = $at->copy()->utc();
            $enquiry->appointment_minutes = $minutes;
            $enquiry->appointment_decided_at = now();
            $enquiry->save();

            $body = $enquiry->team->name.' accepted your appointment request: '.$enquiry->appointmentSummary().'.';
            if (filled($note)) {
                $body .= "\n\n".trim((string) $note);
            }

            $this->enquiries->reply($enquiry, $actor, $body);
            $this->enquiries->setStatus($enquiry->refresh(), $actor, Enquiry::RESOLVED);
        });
    }

    /** The business says no, with a reason the requester reads. */
    public function decline(Enquiry $enquiry, User $actor, string $reason): void
    {
        $this->guardOpen($enquiry);

        DB::transaction(function () use ($enquiry, $actor, $reason) {
            $enquiry->appointment_state = Enquiry::APPOINTMENT_DECLINED;
            $enquiry->appointment_decided_at = now();
            $enquiry->save();

            $this->enquiries->reply($enquiry, $actor, $enquiry->team->name.' declined your appointment request.'."\n\n".trim($reason));
            $this->enquiries->setStatus($enquiry->refresh(), $actor, Enquiry::RESOLVED);
        });
    }

    private function guardOpen(Enquiry $enquiry): void
    {
        if (! $enquiry->isAppointmentRequest() || $enquiry->appointment_state !== Enquiry::APPOINTMENT_REQUESTED) {
            throw ValidationException::withMessages(['decision' => 'This request has already been answered.']);
        }

        if ($enquiry->customer === null) {
            throw ValidationException::withMessages(['decision' => 'The person who asked no longer has an InfoDot account, so there is nobody to answer.']);
        }
    }

    /** The same test as the business page's "Book an appointment" button. */
    private function bookable(Team $team): bool
    {
        return $this->bookings->whyNot(null, $team) === null && $team->appointmentTypes()->active()->exists();
    }
}
