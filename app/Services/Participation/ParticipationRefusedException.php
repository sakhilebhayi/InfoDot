<?php

namespace App\Services\Participation;

use RuntimeException;

/** Dot.Memory understood the request and said no (for example: it holds no consent for this person). */
class ParticipationRefusedException extends RuntimeException
{
    public const CONSENT_REQUIRED = 'consent_required';

    public const INVALID = 'invalid_request';

    public const CONFLICT = 'event_conflict';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('Dot.Memory refused the request ('.$reason.').');
    }
}
