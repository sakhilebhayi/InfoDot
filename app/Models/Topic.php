<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * One subject questions and solutions are filed under. The list is small and curated by moderators. The slug is the
 * stable address (/topics/tasks) and never changes when a topic is renamed; `active` only says whether new content may
 * choose it. A topic that anything uses cannot be deleted.
 */
class Topic extends Model
{
    public const MAX_PER_ITEM = 3;

    public const NAME_MIN = 3;

    public const NAME_MAX = 60;

    protected $fillable = ['slug', 'name', 'description', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $topic) {
            if ($topic->isInUse()) {
                throw new LogicException('A topic that questions or solutions use cannot be deleted. Deactivate it instead.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('topics.active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('topics.sort_order')->orderBy('topics.name')->orderBy('topics.id');
    }

    /** Questions under this topic that the signed-in person may see (the usual visibility rules apply). */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Questions::class, 'question_topic', 'topic_id', 'question_id');
    }

    /** Solutions under this topic that the signed-in person may see (the usual visibility rules apply). */
    public function solutions(): BelongsToMany
    {
        return $this->belongsToMany(Solutions::class, 'solution_topic', 'topic_id', 'solution_id');
    }

    /** Whether any question or solution, whoever may see it, is filed under this topic. */
    public function isInUse(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return DB::table('question_topic')->where('topic_id', $this->getKey())->exists()
            || DB::table('solution_topic')->where('topic_id', $this->getKey())->exists();
    }

    public function url(): string
    {
        return route('topics.show', ['topic' => $this->slug]);
    }
}
