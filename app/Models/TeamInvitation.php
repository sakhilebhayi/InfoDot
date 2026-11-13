<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

/**
 * An invitation to join a company. It lives for a set number of days (config licensing.invitation_days) from when it was
 * sent or sent again. Once expired it cannot be accepted and no longer holds a seat; the person who invited can send it
 * again, which starts a new life.
 *
 * @property int $id
 * @property int $team_id
 * @property string $email
 * @property string|null $role
 * @property Carbon|null $expires_at
 */
class TeamInvitation extends JetstreamTeamInvitation
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'email',
        'role',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            $invitation->expires_at ??= self::freshExpiry();
        });
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** When an invitation sent now runs out. */
    public static function freshExpiry(): Carbon
    {
        return now()->addDays(max(1, (int) config('licensing.invitation_days', 14)));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Invitations that can still be accepted (and so still hold a seat).
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Get the team that the invitation belongs to.
     *
     * @return BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Jetstream::teamModel());
    }
}
