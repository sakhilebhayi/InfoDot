<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A milestone a person reached. See config/achievements.php. Revoked rows are kept (not shown) so earned_at survives. */
class UserAchievement extends Model
{
    protected $fillable = ['user_id', 'key', 'earned_at', 'revoked_at', 'rule_version'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime', 'revoked_at' => 'datetime', 'rule_version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
