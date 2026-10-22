<?php

namespace App\Events\Knowledge;

/**
 * A moderator removed a solution's verification. Forwarded to the points ledger as a reversal of the verification reward.
 * Raised once, after the change is saved; carries ids only (never content or email). `event_key` names the audit row
 * that caused it, so the ledger event id is the same every time this action is replayed. Nothing a browser sends can raise it.
 */
final class SolutionUnverified
{
    /** @param array<string, scalar|null> $facts */
    public function __construct(public readonly string $ref, public readonly array $facts = []) {}
}
