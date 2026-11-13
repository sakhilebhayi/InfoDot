<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the audit trail of the catalogue and of who administers it (R-50): a platform, plan or bundle changed,
 * or the platform administrator role was granted or taken away. Append-only.
 */
class PlatformCatalogueEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
