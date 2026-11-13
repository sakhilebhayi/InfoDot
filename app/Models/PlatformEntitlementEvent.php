<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in a company's platform history (R-50). Written by Entitlements only; never edited or deleted. */
class PlatformEntitlementEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
