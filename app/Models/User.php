<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Billable;
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use Searchable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'points_expiry_emails' => 'boolean',
    ];

    /**
     * TNTSearch reads the primary key out of this array; without 'id' every save of the
     * model throws "Undefined array key id" (it broke registration on 2026-10-17).
     */
    protected static function booted(): void
    {
        static::creating(fn (self $user) => $user->uuid ??= (string) Str::uuid());
    }

    /** Whether the person wants an email before some of their Dot Points lapse. On unless they turned it off. */
    public function wantsPointsExpiryEmails(): bool
    {
        return $this->points_expiry_emails ?? true;
    }

    /** Moderators accept answers on others' questions, verify solutions and handle reports. Granted by an administrator. */
    public function isModerator(): bool
    {
        return (bool) $this->is_moderator;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function avatar(): string
    {
        return 'https://www.gravatar.com/avatar/'.md5($this->email).'?d=mp';
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function questions()
    {
        return $this->hasMany(Questions::class);
    }

    public function solutions()
    {
        return $this->hasMany(Solutions::class);
    }

    public function associates()
    {
        return $this->hasMany(Associates::class);
    }
}
