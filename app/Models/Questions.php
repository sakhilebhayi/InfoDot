<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Questions extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'user_id', 'question', 'description', 'status',
    ];

    public function toSearchableArray(): array
    {
        return [
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

    public function likes()
    {
        return $this->morphMany(Like::class, 'likable');
    }
}
