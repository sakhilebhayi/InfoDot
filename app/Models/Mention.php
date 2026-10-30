<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One person or business pointed at on a question, answer or comment (R-16). Exactly one of mentioned_user_id /
 * mentioned_team_id is set. The row is the resolved identity; the @Name in the text stays readable prose.
 *
 * @property int $id
 * @property string $mentionable_type
 * @property int $mentionable_id
 * @property int $mentioner_id
 * @property int|null $mentioned_user_id
 * @property int|null $mentioned_team_id
 */
class Mention extends Model
{
    public $timestamps = false;

    protected $fillable = ['mentioner_id', 'mentioned_user_id', 'mentioned_team_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    public function mentionedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'mentioned_team_id');
    }
}
