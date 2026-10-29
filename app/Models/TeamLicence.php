<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One company's seat licence: seats bought beyond the included ones, and the state of its billing. */
class TeamLicence extends Model
{
    protected $fillable = ['team_id', 'included_seats', 'purchased_seats', 'billing_status', 'billing_ref', 'grace_until'];

    protected function casts(): array
    {
        return ['included_seats' => 'integer', 'purchased_seats' => 'integer', 'grace_until' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
