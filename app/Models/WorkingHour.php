<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of a recurring week (R-63): on this weekday, from this local time to that one, in the business's time zone. Two rows on one weekday
 * with a gap between them make a break. `booking_provider_id` null means the business's own hours.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $booking_provider_id
 * @property int $weekday 1 Monday to 7 Sunday
 * @property string $starts_at
 * @property string $ends_at
 */
class WorkingHour extends Model
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    protected $fillable = ['booking_provider_id', 'weekday', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<BookingProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(BookingProvider::class, 'booking_provider_id');
    }
}
