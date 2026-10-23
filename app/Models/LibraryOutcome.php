<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a moderator decided about one Dot.Brain recommendation, kept until Dot.Memory has been told. One row per
 * recommendation: deciding it again changes nothing. The delivery columns are managed by LibraryOutcomeOutbox.
 */
class LibraryOutcome extends Model
{
    public const ADOPTED = 'adopted';

    public const DECLINED = 'declined';

    protected $fillable = ['decision_event_id', 'contribution_ref', 'outcome', 'actor_id', 'actor_ref', 'next_attempt_at'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
