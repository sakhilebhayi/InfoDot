<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to describe a company (R-62 phase 2). See the migration for what it keeps. It belongs to one person and is never shown to anyone
 * else; an unconfirmed one is deleted after `config('onboarding.import_retention_days')`.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $team_id
 * @property string $source
 * @property string $status
 * @property ?array<string, array{value: mixed, confidence: float, source: string}> $extracted
 * @property ?array<string, array{values: list<array{value: mixed, confidence: float, source: string}>}> $conflicts
 */
class CompanyProfileImport extends Model
{
    public const MANUAL = 'manual';

    public const WEBSITE = 'website';

    public const DOCUMENT = 'document';

    public const SOURCES = [self::MANUAL, self::WEBSITE, self::DOCUMENT];

    public const DRAFT = 'draft';

    public const CONFIRMED = 'confirmed';

    public const ABANDONED = 'abandoned';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['extracted' => 'array', 'conflicts' => 'array', 'confirmed' => 'array', 'confirmed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
