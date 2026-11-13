<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Several platforms sold together at one price (R-50). The tables exist from step 1; nothing creates a bundle yet. */
class PlatformBundle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'active' => 'boolean', 'amount_minor' => 'integer'];
    }

    /** @return HasMany<PlatformBundleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PlatformBundleItem::class, 'bundle_id');
    }
}
