<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in the audit trail of the catalogue and of who administers it (R-50): a platform, plan or bundle changed,
 * or the platform administrator role was granted or taken away. Append-only.
 */
class PlatformCatalogueEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The catalogue audit trail is append-only: a row cannot be changed.'));
        static::deleting(fn () => throw new LogicException('The catalogue audit trail is append-only: a row cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
