<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;

/** One bookable start time for one appointment type (R-63): UTC instants, and the provider who would take it (null = the business itself). */
final class Slot
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly ?int $providerId,
    ) {}
}
