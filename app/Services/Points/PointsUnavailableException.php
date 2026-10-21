<?php

namespace App\Services\Points;

use RuntimeException;

/**
 * The points service could not give a trustworthy answer: it is not configured, unreachable, timed out, rejected our
 * signature, or answered with something that is not the documented shape. The message is ours and safe to show; it
 * never carries the upstream body, the URL or the secret.
 *
 * For a redemption this means the outcome is UNKNOWN: the request may or may not have been recorded, which is why a
 * retry must reuse the same idempotency key.
 */
class PointsUnavailableException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const UNREACHABLE = 'unreachable';

    public const REJECTED = 'rejected';

    public const MALFORMED = 'malformed';

    public function __construct(public readonly string $reason, string $message = 'Points are unavailable right now.')
    {
        parent::__construct($message);
    }
}
