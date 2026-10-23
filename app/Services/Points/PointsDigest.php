<?php

namespace App\Services\Points;

use Illuminate\Support\Carbon;

/**
 * One points digest the points service wants emailed: what a person earned in the last closed week or month.
 * The service sends no address and no contribution content; the awards are per rule (label, count, points).
 */
final class PointsDigest
{
    /**
     * @param  list<array{rule_key: string, label: string, count: int, points: int}>  $awards
     * @param  array{points: int, first_at: Carbon}|null  $expiring
     */
    public function __construct(
        public readonly string $digestId,
        public readonly string $userUuid,
        public readonly string $period,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
        public readonly int $pointsEarned,
        public readonly array $awards,
        public readonly int $pointsReversed,
        public readonly int $balance,
        public readonly ?array $expiring,
    ) {}
}
