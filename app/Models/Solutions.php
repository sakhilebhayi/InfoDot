<?php

namespace App\Models;

use App\Models\Concerns\HasKnowledgeVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Solutions extends Model
{
    use HasFactory;
    use HasKnowledgeVisibility;
    use Searchable;

    protected $fillable = [
        'user_id', 'team_id', 'visibility', 'solution_title', 'solution_description', 'tags', 'duration', 'duration_type', 'steps',
    ];

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
