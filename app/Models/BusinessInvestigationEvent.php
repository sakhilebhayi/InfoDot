<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in an investigation, with who took it (R-29 §22: every administrative action audited).
 * Append-only.
 *
 * @property int $id
 * @property int $business_investigation_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $note
 * @property Carbon $created_at
 */
class BusinessInvestigationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public const ACTIONS = [
        'opened' => 'Opened the investigation',
        'note' => 'Added a note',
        'report_linked' => 'Linked a report',
        'restricted' => 'Took the business out of discovery',
        'restriction_lifted' => 'Returned the business to discovery',
        'business_told' => 'Wrote to the business',
        'concluded' => 'Concluded',
        'verification_revoked' => 'Revoked verification',
        'appeal_upheld' => 'Appeal upheld',
        'appeal_denied' => 'Appeal denied',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::ACTIONS[$this->action] ?? str_replace('_', ' ', $this->action);
    }
}
