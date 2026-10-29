<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One computed result, kept forever so a score can always be explained and re-derived. Never edited. */
class ReputationSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'rules_version', 'total', 'tier', 'dimensions', 'computed_at'];

    protected function casts(): array
    {
        return ['dimensions' => 'array', 'computed_at' => 'datetime', 'total' => 'integer'];
    }
}
