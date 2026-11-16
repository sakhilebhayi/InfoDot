<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One purchase of paid licences (R-53): a quantity, an interval, the price InfoDot fixed from configuration, and a state that moves only
 * on a signed confirmation from Dot.Finance. A batch is never active until its `paid` event has been verified.
 *
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int $quantity
 * @property string $interval
 * @property int $unit_price_minor
 * @property int $total_minor
 * @property string $currency
 * @property string $status
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $grace_until
 * @property Carbon|null $cancelled_at
 * @property int|null $quantity_next
 * @property string|null $payment_ref
 * @property Carbon|null $last_event_at
 * @property array<string, mixed>|null $price_snapshot
 */
class LicenceBatch extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const GRACE = 'grace';

    public const ENDED = 'ended';

    public const REFUNDED = 'refunded';

    public const MONTH = 'month';

    public const YEAR = 'year';

    public const INTERVALS = [self::MONTH, self::YEAR];

    protected $guarded = ['id', 'uuid', 'status'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'unit_price_minor' => 'integer', 'total_minor' => 'integer', 'quantity_next' => 'integer',
            'period_start' => 'datetime', 'period_end' => 'datetime', 'grace_until' => 'datetime', 'cancelled_at' => 'datetime', 'last_event_at' => 'datetime',
            'price_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $batch) {
            $batch->uuid ??= (string) Str::uuid();
            $batch->status ??= self::PENDING;
        });
    }

    /**
     * Whether this batch's licences count towards the company's capacity right now. Worked out from the dates every time, so a missed
     * scheduler run can never leave an expired batch counting: active until its period ends (with a short tolerance for a renewal that is
     * a few minutes late), in grace or refunded until the grace date. Pending and ended batches never count.
     */
    public function covers(): bool
    {
        return match ($this->status) {
            self::ACTIVE => $this->period_end !== null && $this->period_end->copy()->addMinutes(max(0, (int) config('licensing.period_tolerance_minutes', 60)))->isFuture(),
            self::GRACE, self::REFUNDED => $this->grace_until !== null && $this->grace_until->isFuture(),
            default => false,
        };
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
