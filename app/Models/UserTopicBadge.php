<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A specialist badge: a level in one topic, held while the person's qualifying work reaches it. See config/achievements.php `badges`. */
class UserTopicBadge extends Model
{
    public const CREATED_AT = null;

    protected $fillable = ['user_id', 'topic_id', 'level', 'work_count', 'rules_version'];

    protected function casts(): array
    {
        return ['work_count' => 'integer', 'rules_version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
