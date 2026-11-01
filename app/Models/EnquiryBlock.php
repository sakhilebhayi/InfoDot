<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person a business has stopped from opening new enquiries with it (R-33 §11 abuse
 * prevention). Existing conversations stay readable; the person is not told who blocked them.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property int|null $blocked_by
 * @property Carbon $created_at
 */
class EnquiryBlock extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
