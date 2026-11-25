<?php

namespace App\Services\Booking;

use App\Models\PlatformFeature;

/**
 * Whether appointment booking is open at all: the `bookings` feature of InfoDot in the central feature-control system (R-61). An administrator
 * moves it between available, disabled, development and retired in the platform catalogue. A missing row is NOT treated as available (unlike a
 * platform's own unlisted features): booking opens only when the row says so.
 */
class BookingFeature
{
    public const PLATFORM = 'infodot';

    public const KEY = 'bookings';

    private ?PlatformFeature $row = null;

    private bool $loaded = false;

    public function row(): ?PlatformFeature
    {
        if (! $this->loaded) {
            $this->row = PlatformFeature::query()->where('platform_key', self::PLATFORM)->where('key', self::KEY)->first();
            $this->loaded = true;
        }

        return $this->row;
    }

    public function available(): bool
    {
        return $this->row()?->isAvailable() === true;
    }

    /** What to tell a person when it is not available, or null when it is. */
    public function message(): ?string
    {
        return $this->available() ? null : ($this->row()?->unavailableMessage() ?? 'Appointment booking is not open yet.');
    }
}
