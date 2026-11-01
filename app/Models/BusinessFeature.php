<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A featured placement in the directory (R-33 §8), granted by a reviewer under the published rules
 * and shown with a label that says it is a placement, not a rating or a verification. `basis` is
 * "editorial" for now: paid featuring waits for billing and an owner decision on price.
 *
 * @property int $id
 * @property int $team_id
 * @property string $status
 * @property string $basis
 * @property string|null $reason
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property string|null $decision_note
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $created_at
 */
class BusinessFeature extends Model
{
    public const REQUESTED = 'requested';

    public const ACTIVE = 'active';

    public const DECLINED = 'declined';

    public const ENDED = 'ended';

    public const MAX_DAYS = 30;

    protected $fillable = ['reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @param Builder<BusinessFeature> $query */
    public function scopeShowing(Builder $query): void
    {
        $query->where('status', self::ACTIVE)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
