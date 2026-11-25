<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Booking\BookingIcs;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Something happened to an appointment (R-63). Goes only to a signed-up, verified account (never to an address typed into a booking form), says what
 * happened, when, and with whom, and links to the booking on InfoDot. It never carries the customer's contact details, their reason for the appointment,
 * the business's private notes or how to join an online appointment: those stay on InfoDot behind a sign-in. A customer's confirmation or move comes with a
 * calendar file.
 */
class BookingActivity extends Mailable
{
    public function __construct(
        public readonly string $kind,
        public readonly Booking $booking,
        public readonly User $recipient,
        public readonly bool $toCustomer,
        public readonly ?int $hours = null,
    ) {}

    public function envelope(): Envelope
    {
        $business = $this->plain($this->booking->team->name);
        $type = $this->plain($this->booking->appointmentType->name);

        return new Envelope(subject: match ($this->kind) {
            UserNotice::BOOKING_RECEIVED => 'New booking request for '.$business.': '.$type,
            UserNotice::BOOKING_SUBMITTED => 'Your request to '.$business.' is in',
            UserNotice::BOOKING_CONFIRMED => $business.' confirmed your appointment',
            UserNotice::BOOKING_REJECTED => $business.' could not take your appointment',
            UserNotice::BOOKING_CANCELLED => $this->toCustomer ? $business.' cancelled your appointment' : 'An appointment with '.$business.' was cancelled',
            UserNotice::BOOKING_RESCHEDULED => $business.' moved your appointment',
            UserNotice::BOOKING_RESCHEDULE_REQUESTED => 'A customer asked to move an appointment: '.$type,
            UserNotice::BOOKING_RESCHEDULE_DECLINED => $business.' kept your appointment at its time',
            UserNotice::BOOKING_EXPIRED => $business.' did not answer your request in time',
            UserNotice::BOOKING_REMINDER => 'Reminder: '.$type.' with '.$business.($this->hours === 1 ? ' in an hour' : ' tomorrow'),
            default => 'An update on your appointment with '.$business,
        });
    }

    public function content(): Content
    {
        $zone = ($this->booking->team->bookingSetting?->zone() ?? new \DateTimeZone('Africa/Johannesburg'));
        $local = $this->booking->starts_at->copy()->setTimezone($zone);

        // Names are written by people other than the reader. They go into the mail as plain text only: HTML-escaped by Blade, and with every Markdown
        // character escaped here, so a business or type name cannot make a link, an image or a heading in a mail that comes from InfoDot's own address.
        return new Content(markdown: 'emails.booking-activity', with: [
            'name' => $this->markdownSafe($this->recipient->name),
            'business' => $this->markdownSafe($this->booking->team->name),
            'type' => $this->markdownSafe($this->booking->appointmentType->name),
            'when' => $local->format('l j F Y, H:i').' ('.$zone->getName().')',
            'reference' => $this->booking->reference,
            'kind' => $this->kind,
            'toCustomer' => $this->toCustomer,
            'hours' => $this->hours,
            // What the business wrote to the customer is never put in a mail: the mail says there is a message, and it is read on InfoDot behind the sign-in.
            'hasMessage' => $this->toCustomer && filled($this->booking->status_note) && in_array($this->kind, [UserNotice::BOOKING_REJECTED, UserNotice::BOOKING_CANCELLED, UserNotice::BOOKING_RESCHEDULE_DECLINED], true),
            'url' => $this->toCustomer ? route('my-bookings.show', $this->booking->reference) : route('business.appointments.show', [$this->booking->team, $this->booking]),
            'listUrl' => $this->toCustomer ? route('my-bookings') : route('business.appointments', $this->booking->team),
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->toCustomer || ! in_array($this->kind, [UserNotice::BOOKING_CONFIRMED, UserNotice::BOOKING_RESCHEDULED], true) || $this->booking->status !== Booking::CONFIRMED) {
            return [];
        }

        return [Attachment::fromData(fn () => app(BookingIcs::class)->forBooking($this->booking), 'appointment.ics')->withMime('text/calendar; charset=utf-8; method=PUBLISH')];
    }

    /** A name as it goes into a subject line: no line breaks, control or line-separator characters, and a sensible length. */
    private function plain(string $value): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}\x{FEFF}]+/u', ' ', $value));

        return mb_strlen($value) > 60 ? mb_substr($value, 0, 59).'…' : $value;
    }

    /** Text for the Markdown mail: tidied like a subject, then every character Markdown could read as syntax is backslash-escaped. */
    private function markdownSafe(string $value): string
    {
        return (string) preg_replace('/([\\`*_{}\[\]()#+\-.!|<>~&:@\/"\'])/', '\\$1', $this->plain($value));
    }
}
