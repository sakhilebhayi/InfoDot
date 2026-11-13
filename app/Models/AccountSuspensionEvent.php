<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of the suspension trail: who suspended or restored an account, when and why. Lines are only ever added,
 * so the trail can be trusted afterwards; changing or removing one through the model is refused.
 *
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string|null $reason
 * @property int|null $actor_id
 */
class AccountSuspensionEvent extends Model
{
    public const SUSPENDED = 'suspended';

    public const RESTORED = 'restored';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The suspension trail is append-only.'));
        static::deleting(fn () => throw new LogicException('The suspension trail is append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
