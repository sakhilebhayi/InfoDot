<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One licence reminder already emailed (unique per company, kind and reference), so the reminder job is safe to run twice. */
class LicenceNoticeSent extends Model
{
    public $timestamps = false;

    protected $table = 'licence_notices_sent';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
