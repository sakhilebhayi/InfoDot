<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An account whose details match a verified business's protected identity (R-29 §18–20).
 * A conflict is a reason to look, never a finding: the system never calls the account an
 * impersonator, and the account is not told it is suspected — only that an extra check applies.
 *
 * @property int $id
 * @property int $subject_team_id
 * @property int $protected_team_id
 * @property int|null $verified_identity_id
 * @property list<array{type: string, weight: int, detail: string}> $signals
 * @property int $score
 * @property string $level
 * @property string $status
 * @property string $trigger
 * @property Carbon $last_seen_at
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 * @property Carbon $created_at
 * @property-read Team $subject
 * @property-read Team $protected
 * @property-read User|null $resolver
 */
class IdentityConflict extends Model
{
    public const LEVELS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    /** What the brief (§18) says to do at each level. */
    public const RESPONSES = [
        'low' => 'Allow. Recorded so a reviewer can see it; nobody is told.',
        'medium' => 'Ask for more information before this account can be verified.',
        'high' => 'Review by hand. Verification is withheld and the verified business is told.',
        'critical' => 'Investigate. Verification is withheld and the verified business is told.',
    ];

    public const STATUSES = ['open' => 'Open', 'dismissed' => 'Dismissed — different businesses', 'related' => 'Related businesses', 'escalated' => 'Escalated for investigation'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'score' => 'integer',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'subject_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function protected(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'protected_team_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Medium and above stop the subject from being verified while the conflict is open or escalated. */
    public function blocksVerification(): bool
    {
        return in_array($this->status, ['open', 'escalated'], true) && $this->level !== 'low';
    }
}
