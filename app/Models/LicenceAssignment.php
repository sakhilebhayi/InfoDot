<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Whether one person holds a licence in one company (R-53). A member with no row is licensed: rows exist for people the ledger has had to
 * decide about. `lapsed` (unpaid) and `released` (taken back by the owner) lose platform access and keep their account and data.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property int|null $batch_id
 * @property string $state
 * @property Carbon|null $assigned_at
 * @property Carbon|null $released_at
 */
class LicenceAssignment extends Model
{
    public const ACTIVE = 'active';

    public const RESERVED = 'reserved';

    public const LAPSED = 'lapsed';

    public const RELEASED = 'released';

    /** The two states in which a person has no licence. */
    public const WITHOUT = [self::LAPSED, self::RELEASED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'released_at' => 'datetime'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LicenceBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LicenceBatch::class, 'batch_id');
    }
}
