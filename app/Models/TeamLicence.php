<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One company's seat licence: seats bought beyond the included ones, and the state of its payment.
 *
 * @property int $team_id
 * @property int|null $included_seats
 * @property int $purchased_seats
 * @property string $payment_status
 * @property Carbon|null $grace_until
 * @property Carbon|null $grandfathered_until
 * @property array<string, mixed>|null $grandfather_snapshot
 */
class TeamLicence extends Model
{
    // firstOrCreate does not read the database defaults back, so a new row must carry them itself.
    protected $attributes = ['purchased_seats' => 0, 'payment_status' => 'free'];

    protected $fillable = ['team_id', 'included_seats', 'purchased_seats', 'payment_status', 'payment_ref', 'grace_until', 'grandfathered_until', 'grandfather_snapshot'];

    protected function casts(): array
    {
        return ['included_seats' => 'integer', 'purchased_seats' => 'integer', 'grace_until' => 'datetime', 'grandfathered_until' => 'datetime', 'grandfather_snapshot' => 'array'];
    }

    /** True while a company that existed before the one-free-licence rule still keeps its frozen headcount free. */
    public function isGrandfathered(): bool
    {
        return $this->grandfathered_until !== null && $this->grandfathered_until->isFuture();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
