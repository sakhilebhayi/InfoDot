<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time that cannot be booked (R-63): a holiday or closure, or time a business or one provider has blocked. Instants in UTC, end exclusive.
 * `booking_provider_id` null closes the whole business.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $booking_provider_id
 * @property string $kind
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 */
class BookingClosure extends Model
{
    public const HOLIDAY = 'holiday';

    public const BLOCK = 'block';

    protected $fillable = ['booking_provider_id', 'kind', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
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
