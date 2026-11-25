<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * The calendar file (.ics) that goes with a confirmed or moved appointment (R-63). It holds the business, the appointment type, the time, a reference and a
 * link back to InfoDot, plus the address only for an in-person appointment. It never holds the customer's details, how to join an online appointment, or
 * anything the business wrote as private. Text is escaped and every line folded as RFC 5545 asks, and control characters are dropped, so nothing typed
 * into a name or an address can add a line to the file.
 */
class BookingIcs
{
    public function forBooking(Booking $booking): string
    {
        $booking->loadMissing(['team', 'appointmentType']);
        $type = $booking->appointmentType;
        $inPerson = $type->meeting_format === 'in_person';

        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//InfoDot//Appointments//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:'.$booking->reference.'@infodot.co.za',
            'DTSTAMP:'.CarbonImmutable::now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$booking->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->text($type->name.' with '.$booking->team->name),
        ];

        if ($inPerson && filled($type->location_note)) {
            $lines[] = 'LOCATION:'.$this->text((string) $type->location_note);
        }

        $lines[] = 'DESCRIPTION:'.$this->text('Booking '.$booking->reference.'. Details and changes: '.route('my-bookings.show', $booking->reference));
        $lines[] = 'SEQUENCE:'.$booking->updated_at->getTimestamp(); // a moved appointment replaces the old event instead of duplicating it
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line) => $this->fold($line), $lines))."\r\n";
    }

    /** A value as RFC 5545 TEXT: control characters gone, backslash, semicolon, comma and line breaks escaped. */
    private function text(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{0085}\x{2028}\x{2029}\x{FEFF}]/u', '', $value);
        $value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $value);

        return str_replace(["\r\n", "\r", "\n"], '\n', $value);
    }

    /** Lines longer than 75 octets continue on the next line after a single space; never splitting inside a UTF-8 character. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = [];
        $current = '';

        foreach (mb_str_split($line) as $char) {
            $limit = $out === [] ? 75 : 74;

            if (strlen($current) + strlen($char) > $limit) {
                $out[] = $current;
                $current = '';
            }
            $current .= $char;
        }
        $out[] = $current;

        return implode("\r\n ", $out);
    }
}
