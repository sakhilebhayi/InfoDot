<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** R-59 Phase 1D: a planning target. It is a hope, never a measurement. */
class GrowthTarget extends Model
{
    /** The figures a target may be set for: all of them are measured elsewhere on the page, none is invented. */
    public const METRICS = [
        'businesses' => 'Businesses registered',
        'accounts' => 'Accounts',
        'platform_openers' => 'People who opened a platform',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'value' => 'integer'];
    }
}
