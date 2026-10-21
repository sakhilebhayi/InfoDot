<?php

namespace App\Models;

use App\Models\Concerns\HasKnowledgeVisibility;
use App\Models\Concerns\IsModerated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Questions extends Model
{
    use HasFactory;
    use HasKnowledgeVisibility;
    use IsModerated;
    use Searchable;

    protected $fillable = [
        'user_id', 'team_id', 'visibility', 'question', 'description', 'status',
    ];

    /** Hidden content leaves the search index (and comes back when restored). */
    public function shouldBeSearchable(): bool
    {
        return ! $this->isHidden();
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likable');
    }
}
