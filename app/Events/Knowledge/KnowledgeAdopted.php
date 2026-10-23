<?php

namespace App\Events\Knowledge;

/**
 * A fact about community knowledge, raised once, after the change is saved. Carries ids only (never content or
 * email): the points ledger and the knowledge services decide what it is worth. Nothing a browser sends can raise it.
 */
final class KnowledgeAdopted
{
    /** @param array<string, scalar|null> $facts */
    public function __construct(public readonly string $ref, public readonly array $facts = []) {}
}
