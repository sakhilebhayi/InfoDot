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

    public const SEMINAR_LINK_BROKEN = 'seminar_link_broken';

    public const SEMINAR_DEBT_OPENED = 'seminar_debt_opened';

    public const SEMINAR_DEBT_CLEARED = 'seminar_debt_cleared';

    public const SEMINAR_DEBT_REOPENED = 'seminar_debt_reopened';

    public const SEMINAR_DEBT_WRITTEN_OFF = 'seminar_debt_written_off';

    public const SEMINAR_REMOVED = 'seminar_removed';

    public const SEMINAR_REINSTATED = 'seminar_reinstated';

    public const SEMINAR_APPEAL_DECIDED = 'seminar_appeal_decided';

    public const SEMINAR_REPORT_DECIDED = 'seminar_report_decided';

    /**
     * Notices that are about something the person has booked, paid for or is owed, not about their contributions. The notifications switch
     * silences the second kind only: nobody should miss a cancelled seminar, a changed time, a refund, a dispute or a debt because they
     * switched off "someone answered your question".
     */
    public const SERVICE_KINDS = [
        self::SEMINAR_CANCELLED, self::SEMINAR_CHANGED, self::SEMINAR_SPOT_OPEN, self::SEMINAR_REFUNDED, self::SEMINAR_DISPUTE, self::SEMINAR_LINK_BROKEN,
        self::SEMINAR_DEBT_OPENED, self::SEMINAR_DEBT_CLEARED, self::SEMINAR_DEBT_REOPENED, self::SEMINAR_DEBT_WRITTEN_OFF, self::SEMINAR_REMOVED, self::SEMINAR_REINSTATED, self::SEMINAR_APPEAL_DECIDED,
    ];

    public const KINDS = [
        self::ANSWER_RECEIVED, self::ANSWER_ACCEPTED, self::SOLUTION_VERIFIED, self::KNOWLEDGE_REUSED, self::COMMENT_RECEIVED,
        self::SEMINAR_REMINDER, self::SEMINAR_CANCELLED, self::SEMINAR_CHANGED, self::SEMINAR_SPOT_OPEN, self::SEMINAR_REFUNDED, self::SEMINAR_DISPUTE, self::SEMINAR_LINK_BROKEN,
        self::SEMINAR_DEBT_OPENED, self::SEMINAR_DEBT_CLEARED, self::SEMINAR_DEBT_REOPENED, self::SEMINAR_DEBT_WRITTEN_OFF, self::SEMINAR_REMOVED, self::SEMINAR_REINSTATED, self::SEMINAR_APPEAL_DECIDED,
        self::SEMINAR_REPORT_DECIDED,
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
