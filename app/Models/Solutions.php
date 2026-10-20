<?php

namespace App\Models;

use App\Models\Concerns\HasKnowledgeVisibility;
use App\Models\Concerns\IsModerated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Solutions extends Model
{
    use HasFactory;
    use HasKnowledgeVisibility;
    use IsModerated;
    use Searchable;

    protected $fillable = [
        'user_id', 'team_id', 'visibility', 'solution_title', 'solution_description', 'tags', 'duration', 'duration_type', 'steps',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    /** Hidden content leaves the search index (and comes back when restored). */
    public function shouldBeSearchable(): bool
    {
        return ! $this->isHidden();
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function steps()
    {
        return $this->hasMany(Steps::class);
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
