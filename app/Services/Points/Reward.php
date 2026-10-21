<?php

namespace App\Services\Points;

/** One line of the reward catalogue, as the points service lists it. */
final class Reward
{
    public const KINDS = ['billing_credit', 'capacity', 'marketplace'];

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $kind,
        public readonly ?string $description,
        public readonly int $costPoints,
        public readonly bool $available,
    ) {}

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'billing_credit' => 'Billing credit',
            'capacity' => 'Capacity',
            'marketplace' => 'Marketplace',
            default => ucfirst(str_replace('_', ' ', $kind)),
        };
    }

    /** What the person receives, in plain words, shown before they confirm. */
    public static function kindOutcome(string $kind): string
    {
        return match ($kind) {
            'billing_credit' => 'A credit against your next invoice, applied by an operator.',
            'capacity' => 'More room on your plan, set up by an operator.',
            'marketplace' => 'A marketplace item, handed over by an operator.',
            default => 'Delivered by an operator.',
        };
    }
}
