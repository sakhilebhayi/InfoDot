<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a platform is, for people (R-50): one row per registry key with its description, categories and operational
 * status. The registry in config/ecosystem.php stays the technical record (address, sign-in mode, secrets); this is
 * the record a platform administrator edits.
 *
 * @property string $key
 * @property string $name
 * @property string $operational_status
 * @property bool $discoverable
 * @property bool $is_core
 */
class PlatformCatalogue extends Model
{
    public const OPERATIONAL = 'operational';

    public const DEGRADED = 'degraded';

    public const MAINTENANCE = 'maintenance';

    public const UNAVAILABLE = 'unavailable';

    public const DEVELOPMENT = 'development';

    public const NOT_LAUNCHED = 'not_launched';

    public const STATUSES = [self::OPERATIONAL, self::DEGRADED, self::MAINTENANCE, self::UNAVAILABLE, self::DEVELOPMENT, self::NOT_LAUNCHED];

    protected $table = 'platform_catalogue';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array', 'categories' => 'array', 'connects_with' => 'array',
            'discoverable' => 'boolean', 'is_core' => 'boolean', 'sort' => 'integer',
        ];
    }

    /** People can use it now: working, or working with a known fault. */
    public function isUsable(): bool
    {
        return in_array($this->operational_status, [self::OPERATIONAL, self::DEGRADED], true);
    }

    /**
     * Why people cannot use the platform right now, in plain words, or null when they can (R-61 availability contract).
     * Nothing here says anything about the person or the company: an administrative switch is not a commercial entitlement.
     */
    public function unavailableMessage(): ?string
    {
        return match ($this->operational_status) {
            self::MAINTENANCE => "{$this->name} is under maintenance. Your data is safe and nothing about your account has changed. Please try again later.",
            self::UNAVAILABLE => "{$this->name} is temporarily unavailable. Your data is safe and nothing about your account has changed. Please try again later.",
            self::DEVELOPMENT, self::NOT_LAUNCHED => "{$this->name} is not open to businesses yet.",
            default => null,
        };
    }

    /** Not open to businesses yet (still being built, or built and not launched). */
    public function isNotLaunched(): bool
    {
        return in_array($this->operational_status, [self::DEVELOPMENT, self::NOT_LAUNCHED], true);
    }
}
