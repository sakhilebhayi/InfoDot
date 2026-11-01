<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reviewer's investigation of one business (R-29 §22–23). Internal statuses only — nothing here
 * is ever shown to the public. "Confirmed" is used only as a recorded outcome, with who decided it;
 * the business is told the outcome and may appeal it to a different reviewer.
 *
 * @property int $id
 * @property int $team_id
 * @property string $concern
 * @property string $status
 * @property string $summary
 * @property string|null $message_to_business
 * @property bool $restricted
 * @property int|null $identity_conflict_id
 * @property int|null $opened_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 */
class BusinessInvestigation extends Model
{
    public const OPEN = 'open';

    public const CLEARED = 'cleared';

    public const CONFIRMED_IMPERSONATION = 'confirmed_impersonation';

    public const CONFIRMED_MISCONDUCT = 'confirmed_misconduct';

    public const CORRECTED = 'corrected';

    public const CONCERNS = [
        'potential_impersonation' => 'Potential impersonation',
        'identity_conflict' => 'Identity conflict',
        'reported_concern' => 'Reported concern',
    ];

    /** Internal statuses (R-29 §23). */
    public const STATUSES = [
        self::OPEN => 'Under investigation',
        self::CLEARED => 'Cleared after review',
        self::CONFIRMED_IMPERSONATION => 'Confirmed impersonation',
        self::CONFIRMED_MISCONDUCT => 'Confirmed misleading or fraudulent conduct',
        self::CORRECTED => 'Resolved — the business corrected it',
    ];

    /** Outcomes that keep the business out of discovery until an appeal says otherwise. */
    public const CONFIRMED = [self::CONFIRMED_IMPERSONATION, self::CONFIRMED_MISCONDUCT];

    protected $fillable = ['concern', 'summary', 'message_to_business'];

    protected function casts(): array
    {
        return ['restricted' => 'boolean', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<BusinessInvestigationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BusinessInvestigationEvent::class)->orderBy('id');
    }

    /** @return HasMany<BusinessReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(BusinessReport::class, 'investigation_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** Whether the business has been told about this investigation, so its people may see it. */
    public function isKnownToBusiness(): bool
    {
        return $this->restricted || $this->message_to_business !== null || ! $this->isOpen();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
