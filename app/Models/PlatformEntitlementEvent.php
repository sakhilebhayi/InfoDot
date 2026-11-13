<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One entry in a company's platform history (R-50). Written by Entitlements only; never edited or deleted. */
class PlatformEntitlementEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
