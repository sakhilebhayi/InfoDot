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
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'points_expiry_emails' => 'boolean',
        'achievements_public' => 'boolean',
        'badges_visible' => 'boolean',
        'notices_in_app' => 'boolean',
        'reputation_opt_out' => 'boolean',
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

    /** The official InfoDot account that platform-curated library entries are published under (R-14). */
    public function isOfficialAccount(): bool
    {
        $email = (string) config('knowledge.official.email');

        return $email !== '' && strcasecmp((string) $this->email, $email) === 0;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /**
     * The fallback avatar for a person with no profile photo: their initial, drawn inline. The old Gravatar URL put a hash of the
     * person's email address into every page that showed them (anyone could confirm an address by hashing a guess), and sent each
     * viewer's address and referer to a third party. Nothing leaves the page now.
     */
    public function avatar(): string
    {
        $initial = mb_strtoupper(mb_substr(trim($this->name) ?: '?', 0, 1));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="32" fill="#31394d"/>'
            .'<text x="32" y="42" text-anchor="middle" font-family="sans-serif" font-size="28" fill="#dae2fd">'.e($initial).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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

    /** Whether the person switched the knowledge score off for themselves. Nothing is computed or kept for them while it is. */
    public function optedOutOfReputation(): bool
    {
        return (bool) ($this->reputation_opt_out ?? false);
    }

    /** Whether the person wants in-app notices about their own work. On unless they switched it off. */
    public function wantsInAppNotices(): bool
    {
        return (bool) ($this->notices_in_app ?? true);
    }

    /** Whether the person's specialist badges are shown (beside their answers and on their profile). On unless they hid them. */
    public function showsBadges(): bool
    {
        return (bool) ($this->badges_visible ?? true);
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
