<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasMentions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Comment extends Model
{
    use HasAttachments;
    use HasFactory, Searchable;
    use HasMentions;

    protected $fillable = [
        'body',
    ];

    /**
     * Only the words are indexed. Without this the whole model was sent to the index, relations included: a comment
     * saved with its author attached (as the comment box does) handed TNTSearch an array and failed with a TypeError.
     *
     * @return array{id: mixed, body: string}
     */
    public function toSearchableArray(): array
    {
        return ['id' => $this->getKey(), 'body' => (string) $this->body];
    }

    public function scopeParent(Builder $builder)
    {
        $builder->whereNull('parent_id');
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }
}
