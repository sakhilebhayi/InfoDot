<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The one record that says a company may use a platform (R-50). One row per company and platform; the free core has
 * no rows because every company holds it. Written only by App\Services\Platforms\Entitlements, so every change
 * leaves an event and an outbox message behind.
 *
 * @property int $team_id
 * @property string $platform_key
 * @property string $status
 * @property string $source
 * @property string $access_mode
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $grace_until
 * @property Carbon|null $ended_at
 */
class PlatformEntitlement extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const TRIAL = 'trial';

    public const PAYMENT_REQUIRED = 'payment_required';

    public const EXPIRED = 'expired';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PENDING, self::ACTIVE, self::TRIAL, self::PAYMENT_REQUIRED, self::EXPIRED, self::SUSPENDED, self::CANCELLED];

    public const SOURCES = ['core', 'free', 'unpriced_free', 'included', 'complimentary', 'trial', 'paid', 'enterprise'];

    /** The sources a company can switch on and off by itself, with no payment involved. */
    public const SELF_SERVICE_SOURCES = ['free', 'unpriced_free'];

    public const EVERYONE = 'everyone';

    public const SELECTED = 'selected';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'trial_ends_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'grace_until' => 'datetime',
            'suspended_at' => 'datetime', 'cancelled_at' => 'datetime', 'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Why this entitlement does not grant access now, or null when it does. Active grants until its end date, if it
     * has one; a trial until it ends; a failed payment while the grace period lasts; a cancellation to the end of
     * the period already paid for. Pending waits for the payment confirmation, so it reads as payment required.
     * A trial that ran out on a priced plan also waits for a payment, but nobody was asked for one, so it reads as
     * a trial that ended.
     */
    public function refusal(): ?string
    {
        return match ($this->status) {
            self::ACTIVE => $this->ended_at !== null && $this->ended_at->isPast() ? 'expired' : null,
            self::TRIAL => $this->trial_ends_at !== null && $this->trial_ends_at->isFuture() ? null : 'trial_ended',
            self::PAYMENT_REQUIRED, self::PENDING => $this->status === self::PAYMENT_REQUIRED && $this->grace_until !== null && $this->grace_until->isFuture()
                ? null
                : ($this->status === self::PAYMENT_REQUIRED && $this->source === 'trial' ? 'trial_ended' : 'payment_required'),
            self::CANCELLED => $this->current_period_ends_at !== null && $this->current_period_ends_at->isFuture() ? null : 'cancelled',
            self::EXPIRED => 'expired',
            self::SUSPENDED => 'suspended',
            default => 'not_activated',
        };
    }

    public function grants(): bool
    {
        return $this->refusal() === null;
    }
}
