<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasKnowledgeVisibility;
use App\Models\Concerns\HasTopics;
use App\Models\Concerns\IsModerated;
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
 * @property string $solution_title
 * @property string|null $solution_description
 * @property string|null $tags
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by
 * @property string|null $hidden_reason
 * @property-read User $user
 * @property-read Team|null $team
 */
class Solutions extends Model
{
    use HasAttachments;
    use HasFactory;
    use HasKnowledgeVisibility;
    use HasTopics;
    use IsModerated;
    use Searchable;

    /** Steps go by database cascade, which skips model events: their attachments are cleared here. */
    protected static function booted(): void
    {
        static::deleting(function (self $solution) {
            Attachment::where('attachable_type', (new Steps)->getMorphClass())
                ->whereIn('attachable_id', $solution->steps()->select('id'))
                ->get()->each->delete();
        });
    }

    protected $fillable = [
        'user_id', 'team_id', 'visibility', 'solution_title', 'solution_description', 'tags', 'duration', 'duration_type', 'steps',
    ];

    public function topicLinkTable(): string
    {
        return 'solution_topic';
    }

    public function topicLinkKey(): string
    {
        return 'solution_id';
    }

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    /** Hidden content leaves the search index (and comes back when restored). */
    public function shouldBeSearchable(): bool
    {
        // A company's private items are never indexed: the index has no notion of who may read what.
        return ! $this->isHidden() && $this->isPublic();
    }

    /** A moderator other than the author has checked that the procedure works. */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'solution_title' => $this->solution_title,
            'solution_description' => $this->solution_description,
            'tags' => $this->tags,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function steps()
    {
        return $this->hasMany(Steps::class);
    }

    /** The uploads that sit on this solution's steps (the list pages count them into the indicators). */
    public function stepAttachments()
    {
        return $this->hasManyThrough(Attachment::class, Steps::class, 'solution_id', 'attachable_id')
            ->where('attachments.attachable_type', (new Steps)->getMorphClass());
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likable');
    }
}
