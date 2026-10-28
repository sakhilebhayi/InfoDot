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
        'achievements_public' => 'boolean',
        'notices_in_app' => 'boolean',
        'topic_suggestions' => 'boolean',
        'share_expertise' => 'boolean',
    ];

    /** The summary emails a person can choose between. 'off' is the default: nobody is sent one unless they opt in. */
    public const POINTS_DIGEST_CHOICES = ['off', 'week', 'month'];

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

    /** 'week' or 'month' when the person chose a Dot Points summary email; null (the default) when they did not. */
    public function pointsDigestPeriod(): ?string
    {
        return in_array($this->points_digest, ['week', 'month'], true) ? $this->points_digest : null;
    }

    /** Whether Dot.Memory may remember the topics this person helps with. Off until they switch it on. */
    public function sharesExpertise(): bool
    {
        return (bool) $this->share_expertise;
    }

    /** Whether the person asked to see "questions you might be able to answer" on their points page. Off until they switch it on. */
    public function wantsTopicSuggestions(): bool
    {
        return (bool) $this->topic_suggestions;
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

    public function achievements()
    {
        return $this->hasMany(UserAchievement::class);
    }

    /** Whether the person wants in-app notices about their own work. On unless they switched it off. */
    public function wantsInAppNotices(): bool
    {
        return (bool) ($this->notices_in_app ?? true);
    }

    /** Whether the person lets others see their achievements and public contributions. Off unless they chose it. */
    public function sharesAchievements(): bool
    {
        return (bool) $this->achievements_public;
    }

    public function associates()
    {
        return $this->hasMany(Associates::class);
    }
}
