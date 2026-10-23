<?php

namespace App\Services\Points;

use RuntimeException;

/**
 * The points service understood a digest result report and said no: the digest does not exist (nothing left to do) or
 * the report itself was invalid (a bug on our side). Different from PointsUnavailableException, where the outcome is
 * unknown and the report should be tried again.
 */
class DigestResultRefusedException extends RuntimeException
{
    public const NOT_FOUND = 'digest_not_found';

    public const INVALID = 'validation_failed';

    public function __construct(public readonly string $reason)
    {
        parent::__construct('The points service refused the digest result ('.$reason.').');
    }
}
