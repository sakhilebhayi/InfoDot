<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait RelatesToTeams
{
    /** @phpstan-require-extends Model */

    /** @property int $id */
    public function scopeForCurrentTeam($query): void
    {
        $query->where('team_id', auth()->user()->currentTeam->id);
    }
}
