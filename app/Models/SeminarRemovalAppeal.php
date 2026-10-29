<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A host's one appeal against a moderator removing their seminar. A different moderator decides it. */
class SeminarRemovalAppeal extends Model
{
    public const OPEN = 'open';

    public const REINSTATED = 'reinstated';

    public const DENIED = 'denied';

    protected $fillable = ['seminar_id', 'host_id', 'message', 'status', 'resolved_by', 'resolution_note', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }
}
