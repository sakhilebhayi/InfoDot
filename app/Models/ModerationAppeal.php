<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** The author's one appeal against one hide. A moderator other than the one who hid the content decides it. */
class ModerationAppeal extends Model
{
    public const OPEN = 'open';

    public const UPHELD = 'upheld';

    public const DENIED = 'denied';

    protected $fillable = ['subject_type', 'subject_id', 'hide_action_id', 'appellant_id', 'hidden_by', 'body', 'status'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function appellant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appellant_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
