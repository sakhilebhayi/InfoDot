<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** R-59 Phase 1C: a milestone reward, held for the holding period, then due; reversed if the referrals behind it are. */
class ReferralReward extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['release_at' => 'datetime', 'released_at' => 'datetime', 'reversed_at' => 'datetime'];
    }
}
