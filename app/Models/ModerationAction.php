<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One moderator decision, written once and never changed. The model refuses updates and deletes, so the trail cannot be
 * edited through the application; the actor's uuid is copied in so it survives a deleted account.
 */
class ModerationAction extends Model
{
    public const UPDATED_AT = null;

    public const DISMISSED = 'dismissed';

    public const HIDDEN = 'hidden';

    public const RESTORED = 'restored';

    public const ACCEPTANCE_REVOKED = 'acceptance_revoked';

    public const APPEAL_UPHELD = 'appeal_upheld';

    public const APPEAL_DENIED = 'appeal_denied';

    public const VERIFIED = 'verified';

    public const UNVERIFIED = 'unverified';

    public const APPROVED = 'approved';

    public const TOPIC_ADDED = 'topic_added';

    public const TOPIC_RENAMED = 'topic_renamed';

    public const TOPIC_ACTIVATED = 'topic_activated';

    public const TOPIC_DEACTIVATED = 'topic_deactivated';

    public const TOPICS_SET = 'topics_set';

    public const LIBRARY_ADOPTED = 'library_adopted';

    public const LIBRARY_DECLINED = 'library_declined';

    public const LIBRARY_WITHDRAWN = 'library_withdrawn';

    public const SEMINAR_REMOVED = 'seminar_removed';

    public const ACTIONS = [
        self::DISMISSED, self::HIDDEN, self::RESTORED, self::ACCEPTANCE_REVOKED,
        self::APPEAL_UPHELD, self::APPEAL_DENIED, self::VERIFIED, self::UNVERIFIED, self::APPROVED,
        self::TOPIC_ADDED, self::TOPIC_RENAMED, self::TOPIC_ACTIVATED, self::TOPIC_DEACTIVATED, self::TOPICS_SET,
        self::LIBRARY_ADOPTED, self::LIBRARY_DECLINED, self::LIBRARY_WITHDRAWN, self::SEMINAR_REMOVED,
    ];

    /** What each action is called on screen. */
    public const LABELS = [
        self::DISMISSED => 'Reports dismissed',
        self::HIDDEN => 'Hidden',
        self::RESTORED => 'Restored',
        self::ACCEPTANCE_REVOKED => 'Acceptance removed',
        self::APPEAL_UPHELD => 'Appeal upheld',
        self::APPEAL_DENIED => 'Appeal denied',
        self::VERIFIED => 'Verified',
        self::UNVERIFIED => 'Verification removed',
        self::APPROVED => 'Approved for reward',
        self::TOPIC_ADDED => 'Topic added',
        self::TOPIC_RENAMED => 'Topic renamed',
        self::TOPIC_ACTIVATED => 'Topic switched on',
        self::TOPIC_DEACTIVATED => 'Topic switched off',
        self::TOPICS_SET => 'Topics corrected',
        self::LIBRARY_ADOPTED => 'Adopted into the library',
        self::LIBRARY_DECLINED => 'Library recommendation declined',
        self::LIBRARY_WITHDRAWN => 'Withdrawn from the library',
        self::SEMINAR_REMOVED => 'Seminar removed',
    ];

    protected $fillable = ['actor_id', 'actor_uuid', 'subject_type', 'subject_id', 'action', 'reason', 'report_id', 'appeal_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The moderation trail is append-only: a row cannot be changed.'));
        static::deleting(fn () => throw new LogicException('The moderation trail is append-only: a row cannot be deleted.'));
    }

    public static function record(Model $subject, User $actor, string $action, ?string $reason = null, ?int $reportId = null, ?int $appealId = null): self
    {
        return static::create([
            'actor_id' => $actor->id,
            'actor_uuid' => $actor->uuid,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'reason' => $reason,
            'report_id' => $reportId,
            'appeal_id' => $appealId,
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
