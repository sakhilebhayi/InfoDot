<?php

namespace App\Services\Points;

use RuntimeException;

/**
 * The points service understood the request and said no. Nothing was spent. `reason` is the service's error code (one
 * of the constants below); the message is written here, never copied from the response.
 */
class RedemptionRefusedException extends RuntimeException
{
    public const REWARD_UNAVAILABLE = 'reward_unavailable';

    public const INSUFFICIENT_POINTS = 'insufficient_points';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const MONTHLY_LIMIT = 'monthly_limit_reached';

    public const KEY_REUSED = 'idempotency_key_reused';

    public const VALIDATION_FAILED = 'validation_failed';

    public const RATE_LIMITED = 'rate_limited';

    public const CODES = [
        self::REWARD_UNAVAILABLE, self::INSUFFICIENT_POINTS, self::OUT_OF_STOCK, self::MONTHLY_LIMIT,
        self::KEY_REUSED, self::VALIDATION_FAILED, self::RATE_LIMITED,
    ];

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $pointsNeeded = null,
        public readonly ?int $balance = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Build the refusal for one of the documented codes. Only whitelisted numeric fields are read from the body.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromCode(string $code, array $body, ?int $retryAfterHeader = null): self
    {
        $int = fn (string $key): ?int => isset($body[$key]) && is_numeric($body[$key]) ? max(0, (int) $body[$key]) : null;

        return match ($code) {
            self::REWARD_UNAVAILABLE => new self($code, 'That reward is not available any more. Nothing was spent.'),
            self::INSUFFICIENT_POINTS => self::insufficient($int('points_needed'), $int('balance')),
            self::OUT_OF_STOCK => new self($code, 'That reward has just run out. Nothing was spent.'),
            self::MONTHLY_LIMIT => new self($code, 'This reward has reached its limit for this month. Nothing was spent. Try again next month.'),
            self::KEY_REUSED => new self($code, 'This form was already used for a different reward. Nothing was spent. Reload the page and try again.'),
            self::RATE_LIMITED => self::rateLimited($int('retry_after') ?? $retryAfterHeader),
            default => new self(self::VALIDATION_FAILED, 'We could not process that request. Nothing was spent. Reload the page and try again.'),
        };
    }

    private static function insufficient(?int $needed, ?int $balance): self
    {
        $detail = $needed !== null && $balance !== null
            ? ' It costs '.number_format($needed).' and you have '.number_format($balance).'.'
            : '';

        return new self(self::INSUFFICIENT_POINTS, 'You do not have enough points for this reward.'.$detail.' Nothing was spent.', $needed, $balance);
    }

    private static function rateLimited(?int $seconds): self
    {
        $wait = $seconds !== null && $seconds > 0
            ? ' Try again in about '.max(1, (int) ceil($seconds / 60)).' minute(s).'
            : ' Try again later.';

        return new self(self::RATE_LIMITED, 'You have made a lot of redemption requests in a short time. Nothing was spent.'.$wait, retryAfter: $seconds);
    }
}
