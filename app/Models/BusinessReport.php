<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Someone's concern about a business (R-33 §7, R-29 §21). A reason for a reviewer to look, never a
 * finding: nothing about the business changes because it was reported. Seen only by moderators
 * and, for their own report, the person who made it.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $reporter_id
 * @property string $category
 * @property string $detail
 * @property string $priority
 * @property string $status
 * @property int|null $investigation_id
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 */
class BusinessReport extends Model
{
    public const OPEN = 'open';

    public const INVESTIGATING = 'investigating';

    public const ACTIONED = 'actioned';

    public const DISMISSED = 'dismissed';

    public const STATUSES = [self::OPEN => 'Open', self::INVESTIGATING => 'Under investigation', self::ACTIONED => 'Action taken', self::DISMISSED => 'No problem found'];

    /** What people can report, in their words (R-33 §7 and R-29 §21 together). */
    public const CATEGORIES = [
        'impersonation' => 'It is pretending to be another business',
        'stolen_branding' => 'It is using another business’s logo or branding',
        'incorrect_info' => 'Its information is wrong',
        'fake_registration' => 'Its registration details look false',
        'fraudulent_contact' => 'Its contact details lead somewhere fraudulent',
        'misleading' => 'It misrepresents its services',
        'fraud' => 'It is involved in fraud',
        'suspicious' => 'The profile looks suspicious',
        'inappropriate' => 'Its content is inappropriate',
        'other' => 'Something else',
    ];

    /** Categories that put someone else's money or identity at risk are looked at first. */
    public const SERIOUS = ['impersonation', 'stolen_branding', 'fraud', 'fraudulent_contact', 'fake_registration'];

    public const PRIORITIES = ['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal'];

    protected $fillable = ['category', 'detail'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<BusinessInvestigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(BusinessInvestigation::class, 'investigation_id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::OPEN, self::INVESTIGATING], true);
    }
}
