<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * R-72 (Q-22): a calendar date typed into a form or a URL (Y-m-d), read as midnight in the given zone. Anything that is not a real date
 * (2026-02-30, 2026-13-01, a time, a number, an array) is null, never an exception and never a silently corrected day.
 */
final class LocalDate
{
    public static function parse(mixed $value, DateTimeZone $zone): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $zone);

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }
}
