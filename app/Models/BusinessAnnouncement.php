<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * News the business publishes on its page (R-33 §4): a new branch, a product, opening hours over
 * a holiday. It shows from `published_at` until `ends_at`, when one is set.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $title
 * @property string $body
 * @property Carbon $published_at
 * @property Carbon|null $ends_at
 */
class BusinessAnnouncement extends Model
{
    protected $fillable = ['title', 'body', 'published_at', 'ends_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @param Builder<BusinessAnnouncement> $query */
    public function scopeShowing(Builder $query): void
    {
        $query->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isShowing(): bool
    {
        return $this->published_at->lte(now()) && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
