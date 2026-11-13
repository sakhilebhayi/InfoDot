<?php

namespace App\Services\Platforms;

use App\Models\Team;

/**
 * The answer PlatformAccess gives: allowed or not, the one reason, and the company that was judged. Two answers let a
 * person through without an entitlement being consulted: `not_governed` (InfoDot itself, a bridge service or a key
 * the registry does not hold, all of which keep the behaviour they had before R-50) and `managed_in_platform` (a
 * platform with its own sign-in, which InfoDot cannot enforce and only links to).
 */
final class AccessDecision
{
    public const NOT_SIGNED_IN = 'not_signed_in';

    public const NOT_A_MEMBER = 'not_a_member';

    public const NOT_ACTIVATED = 'not_activated';

    public const NO_PERSONAL_ACCESS = 'no_personal_access';

    public const PAYMENT_REQUIRED = 'payment_required';

    public const TRIAL_ENDED = 'trial_ended';

    public const EXPIRED = 'expired';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    public const NOT_PERMITTED_FOR_YOU = 'not_permitted_for_you';

    public const TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';

    public const NOT_LAUNCHED = 'not_launched';

    public const MANAGED_IN_PLATFORM = 'managed_in_platform';

    public const NOT_GOVERNED = 'not_governed';

    /** Every reason a person can be refused for (R-50 section 5). */
    public const REFUSALS = [
        self::NOT_SIGNED_IN, self::NOT_A_MEMBER, self::NOT_ACTIVATED, self::NO_PERSONAL_ACCESS, self::PAYMENT_REQUIRED, self::TRIAL_ENDED,
        self::EXPIRED, self::SUSPENDED, self::CANCELLED, self::NOT_PERMITTED_FOR_YOU, self::TEMPORARILY_UNAVAILABLE, self::NOT_LAUNCHED,
    ];

    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
        public readonly ?Team $team,
    ) {}

    public static function allow(?Team $team = null, ?string $outcome = null): self
    {
        return new self(true, $outcome, $team);
    }

    public static function refuse(string $reason, ?Team $team = null): self
    {
        return new self(false, $reason, $team);
    }

    public function refused(): bool
    {
        return ! $this->allowed;
    }

    /** True when the platform is opened by its own link because InfoDot cannot enforce access to it. */
    public function managedInPlatform(): bool
    {
        return $this->reason === self::MANAGED_IN_PLATFORM;
    }

    /** True when an entitlement was really consulted (or the platform is core), not waved through. */
    public function governed(): bool
    {
        return ! in_array($this->reason, [self::MANAGED_IN_PLATFORM, self::NOT_GOVERNED], true);
    }
}
