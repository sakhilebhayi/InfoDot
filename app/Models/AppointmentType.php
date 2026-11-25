<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One kind of appointment a business offers (R-63): its length, the spacing of start times, buffers, an optional fee shown as information, the
 * format, and the providers who offer it. A type with no providers is taken by the business itself, on the business's working hours.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $slot_interval_minutes
 * @property int $buffer_before_minutes
 * @property int $buffer_after_minutes
 * @property int|null $fee_cents
 * @property string $currency
 * @property string $meeting_format
 * @property string|null $location_note
 * @property string|null $preparation
 * @property bool|null $requires_approval
 * @property bool $active
 * @property int $sort
 */
class AppointmentType extends Model
{
    public const FORMATS = ['in_person' => 'In person', 'online' => 'Online', 'phone' => 'By phone'];

    public const MIN_DURATION = 5;

    public const MAX_DURATION = 480;

    protected $fillable = ['name', 'description', 'duration_minutes', 'slot_interval_minutes', 'buffer_before_minutes', 'buffer_after_minutes', 'fee_cents', 'currency', 'meeting_format', 'location_note', 'preparation', 'requires_approval', 'active', 'sort'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer', 'slot_interval_minutes' => 'integer', 'buffer_before_minutes' => 'integer', 'buffer_after_minutes' => 'integer',
            'fee_cents' => 'integer', 'requires_approval' => 'boolean', 'active' => 'boolean', 'sort' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** Whether a booking of this type waits for the business to accept it, given the business's own setting. */
    public function needsApproval(BookingSetting $setting): bool
    {
        return $this->requires_approval ?? ($setting->approval_mode === BookingSetting::APPROVAL);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsToMany<BookingProvider, $this> */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(BookingProvider::class, 'appointment_type_provider');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
