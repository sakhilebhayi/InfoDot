<?php

namespace App\Services\Participation;

use RuntimeException;

/**
 * Dot.Memory could not give a trustworthy answer: sharing is not configured, Memory is unreachable or timed out, it
 * rejected our signature, or it answered with something that is not the documented shape. The outcome is UNKNOWN, so
 * nothing is claimed. The message is ours and safe to show; it never carries the upstream body, the URL or the secret.
 */
class ParticipationUnavailableException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const UNREACHABLE = 'unreachable';

    public const REJECTED = 'rejected';

    public const MALFORMED = 'malformed';

    public function __construct(public readonly string $reason, string $message = 'Dot.Memory is unavailable right now.')
    {
        parent::__construct($message);
    }
}
