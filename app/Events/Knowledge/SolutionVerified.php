<?php

namespace App\Events\Knowledge;

/**
 * A moderator (never the author) verified a solution. Forwarded to the points ledger as infodot.solution.verified.
 * Raised once, after the change is saved; carries ids only (never content or email). `event_key` names the audit row
 * that caused it, so the ledger event id is the same every time this action is replayed. Nothing a browser sends can raise it.
 */
final class SolutionVerified
{
    /** @param array<string, scalar|null> $facts */
    public function __construct(public readonly string $ref, public readonly array $facts = []) {}
}
