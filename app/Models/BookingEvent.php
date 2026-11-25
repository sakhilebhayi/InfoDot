<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The audit trail of a business's booking system (R-63): who changed what, and when. `details` holds the fields that changed (before and after)
 * and never a customer's name, contact details or reason for an appointment.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $actor_id
 * @property int|null $booking_id
 * @property string $type
 * @property array<string, mixed>|null $details
 */
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['team_id', 'booking_id', 'actor_id', 'type', 'details'];

    /** An event about one appointment carries its id in `details`; the indexed column is filled from it, so no writer can forget it. */
    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $id = $event->details['booking_id'] ?? null;

            if ($event->booking_id === null && is_int($id)) {
                $event->booking_id = $id;
            }
        });
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
