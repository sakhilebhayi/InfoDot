<?php

namespace App\Models;

use App\Services\Reputation\ReputationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An advisory flag: these accounts accept each other's answers more than ordinary help would. Open and confirmed flags lower
 * the weight of acceptances between their members in the knowledge score (see ReputationEngine); dismissed ones do nothing.
 * Nothing here is shown to the people in it.
 */
/**
 * @property Carbon|null $detected_at
 * @property Carbon|null $reviewed_at
 */
class ReputationRingFlag extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => ReputationEngine::ringFlagsChanged());
        static::deleted(fn () => ReputationEngine::ringFlagsChanged());
    }

    public const OPEN = 'open';

    public const DISMISSED = 'dismissed';

    public const CONFIRMED = 'confirmed';

    public const CYCLE = 'cycle';

    public const CLUSTER = 'cluster';

    protected $fillable = ['kind', 'member_ids', 'member_hash', 'edge_count', 'internal_ratio', 'detected_at', 'status', 'reviewed_by', 'review_note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['member_ids' => 'array', 'edge_count' => 'integer', 'internal_ratio' => 'float', 'detected_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    /** Flags that still lower scores: open until a moderator looks, and confirmed. */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereIn('status', [self::OPEN, self::CONFIRMED]);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param list<int> $ids */
    public static function hashOf(array $ids): string
    {
        $ids = array_map('intval', $ids);
        sort($ids);

        return sha1(json_encode($ids));
    }
}
