<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** R-59 Phase 1C: the append-only trail of everything that happens to a referral or a reward. */
class ReferralEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
