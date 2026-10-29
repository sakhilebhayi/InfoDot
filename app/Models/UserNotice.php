<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing that happened to a person's work. Holds no text and no other person's name; see the migration. */
class UserNotice extends Model
{
    public const ANSWER_RECEIVED = 'answer_received';

    public const ANSWER_ACCEPTED = 'answer_accepted';

    public const SOLUTION_VERIFIED = 'solution_verified';

    public const KNOWLEDGE_REUSED = 'knowledge_reused';

    public const COMMENT_RECEIVED = 'comment_received';

    public const SEMINAR_REMINDER = 'seminar_reminder';

    public const SEMINAR_CANCELLED = 'seminar_cancelled';

    public const SEMINAR_CHANGED = 'seminar_changed';

    public const SEMINAR_SPOT_OPEN = 'seminar_spot_open';

    public const SEMINAR_REFUNDED = 'seminar_refunded';

    public const SEMINAR_DISPUTE = 'seminar_dispute';

    public const KINDS = [
        self::ANSWER_RECEIVED, self::ANSWER_ACCEPTED, self::SOLUTION_VERIFIED, self::KNOWLEDGE_REUSED, self::COMMENT_RECEIVED,
        self::SEMINAR_REMINDER, self::SEMINAR_CANCELLED, self::SEMINAR_CHANGED, self::SEMINAR_SPOT_OPEN, self::SEMINAR_REFUNDED, self::SEMINAR_DISPUTE,
    ];

    public $timestamps = false;

    protected $fillable = ['user_id', 'kind', 'subject_type', 'subject_id', 'ref', 'read_at', 'created_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime', 'subject_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
