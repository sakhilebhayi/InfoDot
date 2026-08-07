<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Like extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'like',
    ];

    public function scopeParent(Builder $builder)
    {
        $builder->whereNull('parent_id');
    }

    public function likable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function children()
    {
        return $this->hasMany(Like::class, 'parent_id')->oldest();
    }
}
