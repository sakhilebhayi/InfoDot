<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A proposed change to an approved solution (R-16): simplify or correct the description or one step, or add a step.
 * Pending until the original author approves, rejects or asks for clarification; the solution itself never changes
 * while a proposal waits. Supporting files ride on the proposal and move to the solution if it is approved.
 *
 * @property int $id
 * @property int $solution_id
 * @property int $proposer_id
 * @property int $base_version
 * @property string $target_kind description|step|new-step
 * @property int|null $step_id
 * @property string|null $proposed_heading
 * @property string $proposed_body
 * @property string $rationale
 * @property string $status pending|clarify|approved|rejected
 * @property string|null $decision_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 */
class SolutionImprovement extends Model
{
    use HasAttachments;

    public const PENDING = 'pending';

    public const CLARIFY = 'clarify';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = ['target_kind', 'step_id', 'proposed_heading', 'proposed_body', 'rationale'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Solutions, $this> */
    public function solution(): BelongsTo
    {
        return $this->belongsTo(Solutions::class, 'solution_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    /** @return BelongsTo<Steps, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(Steps::class, 'step_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::CLARIFY], true);
    }

    /** The solution moved on since this was written: the author must re-evaluate it against the current version. */
    public function isStale(): bool
    {
        return $this->isOpen() && $this->base_version < (int) $this->solution->version;
    }
}
