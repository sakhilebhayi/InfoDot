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

    public const SOLUTION_CONFIRMED = 'solution_confirmed';

    public const MENTIONED = 'mentioned';

    public const SOLUTION_RECOMMENDED = 'solution_recommended';

    public const IMPROVEMENT_PROPOSED = 'improvement_proposed';

    public const IMPROVEMENT_APPROVED = 'improvement_approved';

    public const IMPROVEMENT_REJECTED = 'improvement_rejected';

    public const IMPROVEMENT_CLARIFY = 'improvement_clarify';

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

    /** R-29: a decision or change on the business's verification; the status is carried in `ref`. */
    public const BUSINESS_VERIFICATION = 'business_verification';

    public const BUSINESS_VERIFICATION_RENEWAL = 'business_verification_renewal';

    /** R-29: for reviewers — an application arrived, came back, or a verified identity changed. */
    public const VERIFICATION_QUEUE = 'verification_queue';

    /** R-29 Part C: reviewers — an account's details match a verified business. */
    public const IDENTITY_CONFLICT = 'identity_conflict';

    /** R-29 Part C: the verified business — another account shares its identity details. */
    public const IDENTITY_ALERT = 'identity_alert';

    public const RELATIONSHIP_REQUEST = 'relationship_request';

    public const RELATIONSHIP_DECIDED = 'relationship_decided';

    /** R-33: the InfoDot Inbox — a new enquiry, a reply, an enquiry given to you, a follow-up date reached. */
    public const ENQUIRY_RECEIVED = 'enquiry_received';

    public const ENQUIRY_REPLY = 'enquiry_reply';

    public const ENQUIRY_ASSIGNED = 'enquiry_assigned';

    public const ENQUIRY_FOLLOW_UP = 'enquiry_follow_up';

    /** R-33 Phase 4a: reviewers — a business report or appeal is waiting. */
    public const BUSINESS_TRUST_QUEUE = 'business_trust_queue';

    /** The person who reported a business — their report was reviewed. */
    public const BUSINESS_REPORT_DECIDED = 'business_report_decided';

    /** The business — an investigation was opened, changed or concluded, or an appeal decided. */
    public const BUSINESS_STANDING = 'business_standing';

    /** R-49: ownership of a business was transferred; which side the reader is on is carried in `ref`. */
    public const BUSINESS_OWNERSHIP = 'business_ownership';

    /** R-50: a platform was switched on, switched off, granted, suspended or restored for the company the reader runs. `ref` is "pe:{event}:{platform}:{type}". */
    public const PLATFORM_ENTITLEMENT = 'platform_entitlement';

    /**
     * Notices that are about something the person has booked, paid for or is owed, not about their contributions. The notifications switch
     * silences the second kind only: nobody should miss a cancelled seminar, a changed time, a refund, a dispute or a debt because they
     * switched off "someone answered your question".
     */
    public const SERVICE_KINDS = [
        self::SEMINAR_CANCELLED, self::SEMINAR_CHANGED, self::SEMINAR_SPOT_OPEN, self::SEMINAR_REFUNDED, self::SEMINAR_DISPUTE, self::SEMINAR_LINK_BROKEN,
        self::SEMINAR_DEBT_OPENED, self::SEMINAR_DEBT_CLEARED, self::SEMINAR_DEBT_REOPENED, self::SEMINAR_DEBT_WRITTEN_OFF, self::SEMINAR_REMOVED, self::SEMINAR_REINSTATED, self::SEMINAR_APPEAL_DECIDED,
        self::BUSINESS_VERIFICATION, self::BUSINESS_VERIFICATION_RENEWAL, self::VERIFICATION_QUEUE,
        self::IDENTITY_CONFLICT, self::IDENTITY_ALERT, self::RELATIONSHIP_REQUEST, self::RELATIONSHIP_DECIDED,
        self::ENQUIRY_RECEIVED, self::ENQUIRY_REPLY, self::ENQUIRY_ASSIGNED, self::ENQUIRY_FOLLOW_UP,
        self::BUSINESS_TRUST_QUEUE, self::BUSINESS_REPORT_DECIDED, self::BUSINESS_STANDING, self::BUSINESS_OWNERSHIP,
        self::PLATFORM_ENTITLEMENT,
    ];

    public const KINDS = [
        self::ANSWER_RECEIVED, self::ANSWER_ACCEPTED, self::SOLUTION_VERIFIED, self::KNOWLEDGE_REUSED, self::COMMENT_RECEIVED,
        self::SOLUTION_CONFIRMED, self::MENTIONED, self::SOLUTION_RECOMMENDED,
        self::IMPROVEMENT_PROPOSED, self::IMPROVEMENT_APPROVED, self::IMPROVEMENT_REJECTED, self::IMPROVEMENT_CLARIFY,
        self::SEMINAR_REMINDER, self::SEMINAR_CANCELLED, self::SEMINAR_CHANGED, self::SEMINAR_SPOT_OPEN, self::SEMINAR_REFUNDED, self::SEMINAR_DISPUTE, self::SEMINAR_LINK_BROKEN,
        self::SEMINAR_DEBT_OPENED, self::SEMINAR_DEBT_CLEARED, self::SEMINAR_DEBT_REOPENED, self::SEMINAR_DEBT_WRITTEN_OFF, self::SEMINAR_REMOVED, self::SEMINAR_REINSTATED, self::SEMINAR_APPEAL_DECIDED,
        self::SEMINAR_REPORT_DECIDED,
        self::BUSINESS_VERIFICATION, self::BUSINESS_VERIFICATION_RENEWAL, self::VERIFICATION_QUEUE,
        self::IDENTITY_CONFLICT, self::IDENTITY_ALERT, self::RELATIONSHIP_REQUEST, self::RELATIONSHIP_DECIDED,
        self::ENQUIRY_RECEIVED, self::ENQUIRY_REPLY, self::ENQUIRY_ASSIGNED, self::ENQUIRY_FOLLOW_UP,
        self::BUSINESS_TRUST_QUEUE, self::BUSINESS_REPORT_DECIDED, self::BUSINESS_STANDING, self::BUSINESS_OWNERSHIP,
        self::PLATFORM_ENTITLEMENT,
    ];

    public $timestamps = false;

    protected $fillable = ['user_id', 'kind', 'subject_type', 'subject_id', 'ref', 'read_at', 'created_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime', 'subject_id' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
