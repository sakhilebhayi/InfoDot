<?php

namespace App\Services\Library;

use RuntimeException;

/**
 * Dot.Memory could not give a trustworthy answer: it is not configured, unreachable or timed out, it rejected our
 * signature, or it answered with something that is not the documented shape. The outcome is UNKNOWN, so nothing is
 * claimed or invented. The message is ours and safe to show; it never carries the upstream body, the URL or the secret.
 */
class RecommendationsUnavailableException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const UNREACHABLE = 'unreachable';

    public const REJECTED = 'rejected';

    public const MALFORMED = 'malformed';

    public function __construct(public readonly string $reason, string $message = 'Recommendations are unavailable right now.')
    {
        parent::__construct($message);
    }
}
