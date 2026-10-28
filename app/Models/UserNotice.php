<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing that happened to a person's work. Holds no text and no other person's name; see the migration. */
class UserNotice extends Model
{
    public const ANSWER_RECEIVED = 'answer_received';

    public const ANSWER_ACCEPTED = 'answer_accepted';

    public const SOLUTION_VERIFIED = 'solution_verified';

    public const KNOWLEDGE_REUSED = 'knowledge_reused';

    public const KINDS = [self::ANSWER_RECEIVED, self::ANSWER_ACCEPTED, self::SOLUTION_VERIFIED, self::KNOWLEDGE_REUSED];

    public $timestamps = false;

    protected $fillable = ['user_id', 'kind', 'subject_type', 'subject_id', 'ref', 'read_at', 'created_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime', 'subject_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
