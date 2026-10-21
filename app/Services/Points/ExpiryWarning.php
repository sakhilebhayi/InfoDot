<?php

namespace App\Services\Points;

use Illuminate\Support\Carbon;

/** One expiry warning the points service wants emailed: some of a person's points lapse on a date. */
final class ExpiryWarning
{
    public function __construct(
        public readonly int|string $warningId,
        public readonly string $userUuid,
        public readonly int $points,
        public readonly Carbon $expiresOn,
    ) {}
}
