<?php

namespace App\Services\Participation;

/** What Dot.Memory holds about one person: topic and counts, nothing else. */
final class ParticipationContext
{
    /** @param list<array{topic: string, count: int, kinds: array<string, int>}> $topics */
    public function __construct(
        public readonly bool $granted,
        public readonly array $topics,
    ) {}
}
