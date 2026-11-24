<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** R-59 Phase 1C: how many activated referrals earn how many points. Edited by an administrator. */
class ReferralTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
