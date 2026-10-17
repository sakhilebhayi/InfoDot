<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A pointer to an object another platform owns. See the create_resources migration.
 */
class Resource extends Model
{
    protected $fillable = ['team_id', 'type', 'platform', 'platform_object_uuid', 'path'];

    protected static function booted(): void
    {
        static::creating(function (self $resource) {
            $resource->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
