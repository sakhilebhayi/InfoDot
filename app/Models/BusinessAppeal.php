<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A business asking for a decision about it to be looked at again (R-29 §23, scenario 8): an
 * investigation's restriction or outcome, or a verification rejection, suspension or revocation.
 * One appeal per decision, decided by a reviewer who did not make the original decision.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $appellant_id
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $original_decider
 * @property string $body
 * @property string $status
 * @property int|null $decided_by
 * @property string|null $decision_note
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 */
class BusinessAppeal extends Model
{
    public const OPEN = 'open';

    public const UPHELD = 'upheld';

    public const DENIED = 'denied';

    public const STATUSES = [self::OPEN => 'Waiting for a reviewer', self::UPHELD => 'Upheld', self::DENIED => 'Not upheld'];

    protected $fillable = ['body'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'subject_id' => 'integer'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function appellant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appellant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return MorphMany<Attachment, $this> */
    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order');
    }

    /** The decision being appealed. */
    public function subject(): BusinessInvestigation|BusinessVerification|null
    {
        return $this->subject_type === 'investigation'
            ? BusinessInvestigation::find($this->subject_id)
            : BusinessVerification::find($this->subject_id);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
