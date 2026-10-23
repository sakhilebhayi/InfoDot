<?php

namespace App\Services\Library;

use RuntimeException;

/** Dot.Memory answered, and refused the outcome for good: the decision is unknown to it, or the outcome is malformed. Retrying cannot help. */
class OutcomeRefusedException extends RuntimeException
{
    public const NOT_FOUND = 'decision_not_found';

    public const INVALID = 'invalid_request';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Dot.Memory refused the outcome: '.$reason);
    }
}
