<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One application for InfoDot business verification (R-29 Part B) and, once approved, the
 * business's verified standing. Status changes only through App\Services\Verification\
 * VerificationService — never by mass assignment — and every change is an append-only
 * VerificationEvent. Registration number, phone and address are encrypted at rest; none of this
 * is ever shown on the public business page except the outcome.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $applicant_id
 * @property string $status
 * @property string $category
 * @property string $registered_name
 * @property string|null $trading_name
 * @property string|null $registration_number
 * @property string $registration_country
 * @property string|null $website
 * @property string|null $official_email
 * @property Carbon|null $official_email_confirmed_at
 * @property string|null $email_code_hash
 * @property Carbon|null $email_code_expires_at
 * @property int $email_code_attempts
 * @property string|null $phone
 * @property string|null $address
 * @property string $applicant_role
 * @property Carbon $declared_at
 * @property array<int, string>|null $checks
 * @property string|null $message_to_applicant
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $renewal_reminded_at
 * @property array<string, string|null>|null $identity_snapshot
 * @property Carbon|null $evidence_purged_at
 * @property Carbon $created_at
 * @property-read Team $team
 * @property-read User|null $applicant
 * @property-read User|null $decider
 */
class BusinessVerification extends Model
{
    public const PENDING = 'pending';

    public const UNDER_REVIEW = 'under_review';

    public const INFO_REQUIRED = 'info_required';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    public const REVOKED = 'revoked';

    public const EXPIRED = 'expired';

    public const WITHDRAWN = 'withdrawn';

    /** A newer approval took over the business's standing. Internal; never shown as a status. */
    public const SUPERSEDED = 'superseded';

    /** An application still being worked on — one per business at a time. */
    public const OPEN = [self::PENDING, self::UNDER_REVIEW, self::INFO_REQUIRED];

    public const STATUS_LABELS = [
        self::PENDING => 'Submitted',
        self::UNDER_REVIEW => 'Under review',
        self::INFO_REQUIRED => 'More information needed',
        self::VERIFIED => 'Verified',
        self::REJECTED => 'Not approved',
        self::SUSPENDED => 'Suspended',
        self::REVOKED => 'Revoked',
        self::EXPIRED => 'Lapsed',
        self::WITHDRAWN => 'Withdrawn',
        self::SUPERSEDED => 'Renewed',
    ];

    public const CATEGORIES = [
        'registered_company' => 'Registered company',
        'sole_proprietor' => 'Sole proprietor',
        'other_entity' => 'Partnership, trust, cooperative or other entity',
    ];

    /** What a reviewer can confirm. The badge panel names exactly the ones confirmed. */
    public const CHECKS = [
        'registration_document' => 'Registration or founding documents reviewed by InfoDot',
        'official_email' => 'Control of an email address at the business’s own website domain',
        'representative_authority' => 'The applicant’s authority to act for the business, by document',
        'operating_presence' => 'Evidence the business operates at the stated address (e.g. a utility account or lease)',
    ];

    protected $fillable = [
        'category', 'registered_name', 'trading_name', 'registration_number', 'registration_country',
        'website', 'official_email', 'phone', 'address', 'applicant_role',
    ];

    protected $hidden = ['email_code_hash', 'registration_number', 'phone', 'address'];

    protected function casts(): array
    {
        return [
            'registration_number' => 'encrypted',
            'phone' => 'encrypted',
            'address' => 'encrypted',
            'official_email_confirmed_at' => 'datetime',
            'email_code_expires_at' => 'datetime',
            'declared_at' => 'datetime',
            'decided_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'renewal_reminded_at' => 'datetime',
            'evidence_purged_at' => 'datetime',
            'checks' => 'array',
            'identity_snapshot' => 'array',
            'email_code_attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<VerificationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(VerificationEvent::class)->orderBy('id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->where('role', 'evidence')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Verified and in date: the only state that shows the badge. */
    public function isCurrent(): bool
    {
        return $this->status === self::VERIFIED && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function label(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** The website's host without "www.", lower case — what an official email must sit under. */
    public function websiteHost(): ?string
    {
        $host = $this->website ? parse_url($this->website, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }
}
