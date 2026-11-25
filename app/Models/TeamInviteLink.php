<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A shareable link into a company (R-62 phase 4). The token itself is never stored, only its SHA-256 hash, so the link can be shown once
 * and nobody with database access can rebuild it. A link brings people in as members, ends on a date, may have a limit on uses, and can be revoked.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property string $token_hash
 * @property string $role
 * @property int|null $max_uses
 * @property int $uses
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 */
class TeamInviteLink extends Model
{
    public const DAYS = 7;

    public const MAX_DAYS = 30;

    public const MAX_USES_CEILING = 500;

    protected $fillable = ['team_id', 'created_by', 'token_hash', 'role', 'max_uses', 'uses', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return array{0: self, 1: string} the saved link and the plain token (shown once, never stored) */
    public static function issue(Team $team, ?User $by, int $days = self::DAYS, ?int $maxUses = null): array
    {
        $token = Str::random(48);
        $link = self::create([
            'team_id' => $team->getKey(),
            'created_by' => $by?->getKey(),
            'token_hash' => self::hash($token),
            'role' => 'member',
            'max_uses' => $maxUses === null ? null : max(1, min(self::MAX_USES_CEILING, $maxUses)),
            'uses' => 0,
            'expires_at' => now()->addDays(max(1, min(self::MAX_DAYS, $days))),
        ]);

        return [$link, $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        return self::where('token_hash', self::hash($token))->first();
    }

    /** Why the link cannot be used right now, or null when it can. */
    public function problem(): ?string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            $this->max_uses !== null && $this->uses >= $this->max_uses => 'used_up',
            default => null,
        };
    }

    public function status(): string
    {
        return $this->problem() ?? 'active';
    }

    /** @param  Builder<self>  $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now())
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'));
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
