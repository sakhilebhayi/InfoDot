<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReport extends Model
{
    public const REASONS = ['spam', 'duplicate', 'low_quality', 'harmful', 'other'];

    public const OPEN = 'open';

    public const ACTIONED = 'actioned';

    public const DISMISSED = 'dismissed';

    public const STATUSES = [self::OPEN, self::ACTIONED, self::DISMISSED];

    /** What each reason is called on screen. */
    public const REASON_LABELS = [
        'spam' => 'Spam',
        'duplicate' => 'Duplicate',
        'low_quality' => 'Low quality',
        'harmful' => 'Harmful or misleading',
        'other' => 'Something else',
    ];

    protected $fillable = ['reportable_type', 'reportable_id', 'reporter_id', 'reason', 'detail', 'status'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Has this person ever reported this item, whatever became of the report? */
    public static function existsFor(Model $item, User $reporter): bool
    {
        return static::query()
            ->where('reportable_type', $item->getMorphClass())
            ->where('reportable_id', $item->getKey())
            ->where('reporter_id', $reporter->id)
            ->exists();
    }
}
