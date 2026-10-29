<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Billing callback that was handled, so a repeat is applied once. */
class SeminarTicketEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['event', 'ticket_ref', 'outcome', 'created_at'];
}
