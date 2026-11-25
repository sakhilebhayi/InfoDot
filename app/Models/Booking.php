<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One appointment (R-63). `starts_at` and `ends_at` are UTC instants; `blocks_from` and `blocks_until` add the type's buffers and are what the
 * availability engine compares, so a buffer holds the time even though the appointment itself is shorter. A booking in a HOLDING status (pending or
 * confirmed) holds its time; a cancelled, rejected or completed one does not.
 *
 * @property int $id
 * @property string|null $reference
 * @property int $team_id
 * @property int $appointment_type_id
 * @property int|null $booking_provider_id
 * @property int|null $customer_user_id
 * @property int|null $customer_team_id
 * @property string $customer_name
 * @property string $customer_email
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon $blocks_from
 * @property Carbon $blocks_until
 * @property string $status
 * @property string|null $status_note
 * @property Carbon|null $reschedule_requested_at
 * @property string|null $reschedule_note
 */
class Booking extends Model
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const REJECTED = 'rejected';

    public const CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';

    public const CANCELLED_BY_BUSINESS = 'cancelled_by_business';

    public const COMPLETED = 'completed';

    public const NO_SHOW = 'no_show';

    /** A request the business never answered: it was let go so it stops holding the time (R-63 phase 5). */
    public const EXPIRED = 'expired';

    /** The statuses that hold the time: nobody else can take it. */
    public const HOLDING = [self::PENDING, self::CONFIRMED];

    public const STATUSES = [self::PENDING, self::CONFIRMED, self::REJECTED, self::CANCELLED_BY_CUSTOMER, self::CANCELLED_BY_BUSINESS, self::COMPLETED, self::NO_SHOW, self::EXPIRED];

    protected $fillable = [
        'reference', 'team_id', 'appointment_type_id', 'booking_provider_id', 'customer_user_id', 'customer_name', 'customer_email', 'customer_phone',
        'customer_company', 'customer_team_id', 'reason', 'answers', 'starts_at', 'ends_at', 'blocks_from', 'blocks_until', 'status', 'internal_notes', 'cancelled_at', 'terms_accepted_at', 'status_note', 'reschedule_requested_at', 'reschedule_note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'blocks_from' => 'datetime', 'blocks_until' => 'datetime', 'cancelled_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'reschedule_requested_at' => 'datetime', 'answers' => 'array',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeHolding(Builder $query): void
    {
        $query->whereIn('status', self::HOLDING);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<AppointmentType, $this> */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    /** @return BelongsTo<BookingProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(BookingProvider::class, 'booking_provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /** @return BelongsTo<Team, $this> the company the person booked or wrote for (R-66), or none when they acted for themselves */
    public function customerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'customer_team_id');
    }
}
