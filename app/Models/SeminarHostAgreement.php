<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** That a host accepted a version of the host agreement, and when. Dot.Billing is told too, and refuses tickets without it. */
class SeminarHostAgreement extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'version', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
