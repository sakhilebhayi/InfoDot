<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One feature inside a platform that an administrator can switch off without switching the platform off (R-61). The availability
 * `state` and the commercial `kind` are independent: a feature can be free and disabled, or paid and available.
 *
 * @property string $platform_key
 * @property string $key
 * @property string $name
 * @property string $state
 * @property string $kind
 */
class PlatformFeature extends Model
{
    public const AVAILABLE = 'available';

    public const DISABLED = 'disabled';

    public const DEVELOPMENT = 'development';

    public const RETIRED = 'retired';

    public const STATES = [self::AVAILABLE, self::DISABLED, self::DEVELOPMENT, self::RETIRED];

    public const KINDS = ['free', 'paid'];

    protected $guarded = [];

    public function isAvailable(): bool
    {
        return $this->state === self::AVAILABLE;
    }

    /** Plain wording for a person who meets the feature while it is not available. */
    public function unavailableMessage(): ?string
    {
        return match ($this->state) {
            self::DISABLED => "{$this->name} is temporarily unavailable. Your data is safe and nothing about your account has changed. Please try again later.",
            self::DEVELOPMENT => "{$this->name} is being improved and is not open right now.",
            self::RETIRED => "{$this->name} is no longer offered.",
            default => null,
        };
    }
}
