<?php

namespace App\Services\Points;

use Illuminate\Support\Carbon;

/** One row of a person's redemption history. */
final class RedemptionRecord
{
    public const STATUSES = ['pending', 'fulfilled', 'failed', 'cancelled'];

    public function __construct(
        public readonly string $redemptionId,
        public readonly string $rewardName,
        public readonly string $rewardKind,
        public readonly int $pointsSpent,
        public readonly string $status,
        public readonly Carbon $requestedAt,
        public readonly ?Carbon $resolvedAt,
        public readonly bool $pointsReturned,
        public readonly ?string $message,
    ) {}

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'fulfilled' => 'Fulfilled',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
    }
}
