<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A conversation between a person and a business in the InfoDot Inbox (R-33 §5A). It belongs to
 * the business (the Team), never to one employee: the owner and administrators see every
 * enquiry, and a member sees the ones assigned to them. The customer sees only the messages
 * exchanged — never internal notes, events or who inside the business handled it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int|null $user_id
 * @property string $subject
 * @property string $category
 * @property string $status
 * @property int|null $assigned_to
 * @property Carbon|null $last_customer_message_at
 * @property Carbon|null $last_business_message_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $follow_up_on
 * @property Carbon|null $follow_up_notified_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Enquiry extends Model
{
    public const NEW = 'new';

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const AWAITING = 'awaiting';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    /** How the business sees each status. */
    public const STATUSES = [
        self::NEW => 'New',
        self::OPEN => 'Open',
        self::IN_PROGRESS => 'In progress',
        self::AWAITING => 'Awaiting response',
        self::RESOLVED => 'Resolved',
        self::CLOSED => 'Closed',
    ];

    /** How the customer sees the same status: what it means for them. */
    public const CUSTOMER_STATUSES = [
        self::NEW => 'Sent',
        self::OPEN => 'Open',
        self::IN_PROGRESS => 'Being handled',
        self::AWAITING => 'Waiting for your reply',
        self::RESOLVED => 'Resolved',
        self::CLOSED => 'Closed',
    ];

    protected $fillable = ['subject', 'category'];

    protected function casts(): array
    {
        return [
            'last_customer_message_at' => 'datetime',
            'last_business_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'follow_up_on' => 'date',
            'follow_up_notified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $e) => $e->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<EnquiryMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(EnquiryMessage::class)->orderBy('id');
    }

    /** @param Builder<Enquiry> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', [self::RESOLVED, self::CLOSED]);
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }

    /** The person who opened it. */
    public function isCustomer(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /**
     * Someone who answers for the business: its owner or an administrator, or the member it is
     * assigned to while they are still in the business.
     */
    public function isHandler(?User $user): bool
    {
        if ($user === null || $this->team === null) {
            return false;
        }

        if ($user->ownsTeam($this->team) || $user->hasTeamRole($this->team, 'admin')) {
            return true;
        }

        return $this->assigned_to === $user->id && $user->belongsToTeam($this->team);
    }

    public function categoryLabel(): string
    {
        return config('enquiries.categories.'.$this->category, 'Enquiry');
    }

    /** Whether the business side has something this person has not opened yet. */
    public function unreadForHandler(?Carbon $readAt): bool
    {
        return $this->last_customer_message_at !== null && ($readAt === null || $this->last_customer_message_at->gt($readAt));
    }

    public function unreadForCustomer(?Carbon $readAt): bool
    {
        return $this->last_business_message_at !== null && ($readAt === null || $this->last_business_message_at->gt($readAt));
    }
}
