<?php

namespace Tests\Unit;

use App\Support\LocalDate;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** R-72 (Q-22): one parser for a typed calendar date, shared by the booking screens. */
class LocalDateTest extends TestCase
{
    public function test_a_real_date_is_midnight_in_the_zone(): void
    {
        $date = LocalDate::parse('2026-12-01', new DateTimeZone('Africa/Johannesburg'));

        $this->assertSame('2026-12-01 00:00:00 +02:00', $date?->format('Y-m-d H:i:s P'));
    }

    public function test_anything_else_is_null(): void
    {
        $zone = new DateTimeZone('UTC');

        foreach (['2026-02-30', '2026-13-01', '2026-1-01', '2026-12-01 09:00', '', ' 2026-12-01'] as $value) {
            $this->assertNull(LocalDate::parse($value, $zone), $value);
        }
        $this->assertNull(LocalDate::parse(20261201, $zone));
        $this->assertNull(LocalDate::parse(['2026-12-01'], $zone));
        $this->assertNull(LocalDate::parse(null, $zone));
    }
}
