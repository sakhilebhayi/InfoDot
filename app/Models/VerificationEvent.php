<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One thing that happened to a verification (R-29 §13 "maintain a history of verification
 * decisions and changes"). Append-only: rows are never updated or deleted by the application.
 *
 * @property int $id
 * @property int $business_verification_id
 * @property int $team_id
 * @property int|null $actor_id
 * @property string $event
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
class VerificationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Verification history is append-only.'));
        static::deleting(fn () => throw new LogicException('Verification history is append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
