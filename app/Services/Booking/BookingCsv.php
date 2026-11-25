<?php

namespace App\Services\Booking;

/**
 * Writes the booking export as CSV. A cell that starts like a spreadsheet formula is prefixed so it opens as text, never as something that runs
 * (a customer chose their own name, and a spreadsheet would otherwise run "=HYPERLINK(...)").
 */
class BookingCsv
{
    /** @var array<string, string> */
    public const COLUMNS = [
        'reference' => 'Reference', 'status' => 'Status', 'appointment' => 'Appointment', 'provider' => 'Provider', 'starts' => 'Starts', 'ends' => 'Ends',
        'minutes' => 'Minutes', 'created' => 'Requested', 'customer' => 'Customer', 'email' => 'Email', 'phone' => 'Phone', 'company' => 'Company',
    ];

    public static function cell(mixed $value): string
    {
        $text = (string) $value;

        if ($text === '') {
            return $text;
        }

        // A plain phone number or figure may start with + or -; anything else that starts like a formula is made text.
        $risky = strpbrk($text[0], "=@\t\r\n") !== false
            || (strpbrk($text[0], '+-') !== false && preg_match('/^[+-][0-9][0-9 ()-]*$/', $text) !== 1);

        return $risky ? "'".$text : $text;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  resource  $out
     */
    public static function write($out, iterable $rows): void
    {
        fwrite($out, "\xEF\xBB\xBF"); // a byte-order mark, so Excel reads names with accents correctly
        fputcsv($out, array_values(self::COLUMNS), ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, array_map(fn (string $key) => self::cell($row[$key] ?? ''), array_keys(self::COLUMNS)), ',', '"', '');
        }
    }
}
