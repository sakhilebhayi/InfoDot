<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One payment event that Dot.Finance confirmed with a valid signature (R-50). Written only by
 * App\Services\Platforms\PaymentConfirmations, once per event id, and never changed or deleted: it is the evidence
 * behind every entitlement whose source is `paid`, and the only thing the confirmed-payments view counts.
 *
 * @property string $event_id
 * @property string $event
 * @property int $team_id
 * @property string $platform_key
 * @property int|null $plan_id
 * @property int|null $bundle_id
 * @property int|null $amount_minor
 * @property string|null $currency
 * @property string|null $reference
 * @property Carbon|null $period_ends_at
 * @property Carbon|null $occurred_at
 * @property string $outcome
 */
class PlatformPaymentConfirmation extends Model
{
    public const PAID = 'paid';

    public const RENEWED = 'renewed';

    public const PAYMENT_FAILED = 'payment_failed';

    public const REFUNDED = 'refunded';

    public const CANCELLED = 'cancelled';

    public const EVENTS = [self::PAID, self::RENEWED, self::PAYMENT_FAILED, self::REFUNDED, self::CANCELLED];

    public const APPLIED = 'applied';

    public const IGNORED = 'ignored';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'period_ends_at' => 'datetime', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('A confirmed payment event is kept for good: a row cannot be deleted.'));
    }

    /** True for the two events that say money was received. */
    public function confirmsPayment(): bool
    {
        return in_array($this->event, [self::PAID, self::RENEWED], true);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<PlatformPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }
}
