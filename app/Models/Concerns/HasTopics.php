<?php

namespace App\Models\Concerns;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * A question or solution that is filed under topics. The models name their own link table and key.
 *
 * `openToAll` is the one definition of "public": published for everyone and not hidden by a moderator. Counts and
 * suggestions use it with the global scopes lifted, so what a person can see themselves never changes what is counted.
 */
trait HasTopics
{
    abstract public function topicLinkTable(): string;

    abstract public function topicLinkKey(): string;

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, $this->topicLinkTable(), $this->topicLinkKey(), 'topic_id');
    }

    /** Filed under this topic (a Topic, its id or its slug). */
    public function scopeInTopic(Builder $query, Topic|int|string $topic): Builder
    {
        $table = $this->getTable();

        return $query->whereIn($table.'.id', function ($sub) use ($topic) {
            $sub->select($this->topicLinkKey())->from($this->topicLinkTable())
                ->when(is_string($topic) && ! ctype_digit($topic), fn ($q) => $q->whereIn('topic_id', DB::table('topics')->select('id')->where('slug', $topic)))
                ->when($topic instanceof Topic, fn ($q) => $q->where('topic_id', $topic->getKey()))
                ->when(is_int($topic) || (is_string($topic) && ctype_digit($topic)), fn ($q) => $q->where('topic_id', (int) $topic));
        });
    }

    /** Not filed under any topic yet. */
    public function scopeUntagged(Builder $query): Builder
    {
        return $query->whereNotIn($this->getTable().'.id', DB::table($this->topicLinkTable())->select($this->topicLinkKey()));
    }

    /** Published to the whole community and not hidden. Use with withoutGlobalScopes() when counting. */
    public function scopeOpenToAll(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->where($table.'.visibility', self::VISIBILITY_PUBLIC)->whereNull($table.'.hidden_at');
    }
}
