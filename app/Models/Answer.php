<?php

namespace App\Models;

use App\Models\Concerns\IsModerated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Answer extends Model
{
    use IsModerated;
    use SoftDeletes;

    public const PUBLISHED = 'published';

    public const FLAGGED = 'flagged';

    public const REMOVED = 'removed';

    protected $fillable = ['question_id', 'user_id', 'body', 'body_hash', 'status', 'is_self_answer'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'is_self_answer' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->uuid ??= (string) Str::uuid());
    }

    /** Normalised so that spacing and case tricks do not make the same answer look new. */
    public static function hashBody(string $body): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($body))));
    }

    public function question(): BelongsTo
    {
        // Unscoped on purpose: who may see the question is decided explicitly by AnswerService::canSee().
        return $this->belongsTo(Questions::class, 'question_id')->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function reference(): string
    {
        return 'infodot:answer:'.$this->id;
    }
}
