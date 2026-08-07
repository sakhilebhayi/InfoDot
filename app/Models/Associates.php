<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Associates extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'associate_id',
    ];

    public function user()
    {
        return $this->belongsToMany(User::class);
    }
}
