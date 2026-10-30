<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (user, platform) that has signed in through the identity provider. Powers the
 * connected-platforms view, platform health, and decides who gets a back-channel logout when the
 * central session ends (R-20 §10, §13).
 */
class SsoPlatformSession extends Model
{
    protected $guarded = [];

    protected $casts = ['last_issued_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DotPlatform, $this> */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(DotPlatform::class, 'dot_platform_id');
    }
}
