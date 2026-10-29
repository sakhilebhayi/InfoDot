<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A signed-in person's report that a seminar is misleading, harmful, spam or otherwise wrong. One per person per seminar; moderators decide. */
class SeminarReport extends Model
{
    public const REASONS = ['misleading' => 'Misleading or not what it says', 'harmful' => 'Harmful or unsafe', 'spam' => 'Spam or a scam', 'other' => 'Something else'];

    protected $fillable = ['seminar_id', 'reporter_id', 'reason', 'detail', 'status', 'resolved_by', 'resolution_note', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withDefault(['name' => 'A former member']);
    }
}
