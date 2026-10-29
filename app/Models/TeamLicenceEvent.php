<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One entry in a company's seat audit trail. Written by SeatLedger only; never edited. */
class TeamLicenceEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['team_id', 'actor_id', 'type', 'subject', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
