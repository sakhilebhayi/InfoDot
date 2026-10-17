<?php

namespace App\Models;

use App\Models\Concerns\HasKnowledgeVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Questions extends Model
{
    use HasFactory;
    use HasKnowledgeVisibility;
    use Searchable;

    protected $fillable = [
        'user_id', 'team_id', 'visibility', 'question', 'description', 'status',
    ];

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
