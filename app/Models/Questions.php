<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasKnowledgeVisibility;
use App\Models\Concerns\HasTopics;
use App\Models\Concerns\IsModerated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property string $visibility public or private (a company's own)
 * @property string $question
 * @property string|null $description
 * @property int|null $status 1 once an answer is accepted
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by
 * @property string|null $hidden_reason
 * @property-read User $user
 * @property-read Team|null $team
 */
class Questions extends Model
{
    use HasAttachments;
    use HasFactory;
    use HasKnowledgeVisibility;
    use HasTopics;
    use IsModerated;
    use Searchable;

    protected $fillable = [
        'user_id', 'team_id', 'visibility', 'question', 'description', 'status',
    ];

    public function topicLinkTable(): string
    {
        return 'question_topic';
    }

    public function topicLinkKey(): string
    {
        return 'question_id';
    }

    /**
     * Nobody has given a real answer yet: it is not marked solved and has no published, visible answer from somebody
     * other than the asker. Written against the table, not the Answer model, so who is looking cannot change it.
     */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('questions.status')->orWhere('questions.status', '!=', 1))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('answers')
                ->whereColumn('answers.question_id', 'questions.id')
                ->where('answers.status', Answer::PUBLISHED)
                ->where('answers.is_self_answer', false)
                ->whereNull('answers.hidden_at')
                ->whereNull('answers.deleted_at'));
    }

    /** Hidden content leaves the search index (and comes back when restored). */
    public function shouldBeSearchable(): bool
    {
        // A company's private items are never indexed: the index has no notion of who may read what.
        return ! $this->isHidden() && $this->isPublic();
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'question' => $this->question,
            'description' => $this->description,
        ];
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likable');
    }
}
