<?php

namespace App\Services\Points;

/** What the points service answered to a redeem request that it accepted (201), or recognised as a repeat (200). */
final class RedemptionReceipt
{
    public function __construct(
        public readonly string $redemptionId,
        public readonly string $status,
        public readonly int $rewardId,
        public readonly string $rewardName,
        public readonly string $rewardKind,
        public readonly int $pointsSpent,
        public readonly int $balanceAfter,
        public readonly bool $replayed,
    ) {}
}
