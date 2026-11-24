<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** R-59 Phase 1C: one person who registered through a business's code, and how far they have got. */
class Referral extends Model
{
    public const REGISTERED = 'registered';

    public const VERIFIED = 'verified';

    public const ACTIVATED = 'activated';

    public const REVERSED = 'reversed';

    public const REFUSED = 'refused';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime', 'verified_at' => 'datetime', 'activated_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    /** @return BelongsTo<ReferralCode, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
