<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A free seminar a person hosts. The join link is the host's own; see config/seminars.php. */
class Seminar extends Model
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['removed_at', 'removed_reason', 'removed_refunds', 'public_code', 'join_url_status', 'join_url_checked_at', 'p2p_quality', 'p2p_uplink_kbps', 'delivery', 'price_cents', 'currency', 'host_id', 'topic_id', 'title', 'summary', 'description', 'starts_at', 'duration_minutes', 'capacity', 'join_url', 'status', 'cancelled_at'];

    protected function casts(): array
    {
        return ['join_url_checked_at' => 'datetime', 'removed_at' => 'datetime', 'starts_at' => 'datetime', 'cancelled_at' => 'datetime', 'duration_minutes' => 'integer', 'capacity' => 'integer', 'price_cents' => 'integer'];
    }

    protected static function booted(): void
    {
        // Every seminar gets a short InfoDot link that always opens, so a host never has to hand out a typed address.
        static::creating(function (self $seminar) {
            $seminar->public_code ??= \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
        });
    }

    public function shareUrl(): string
    {
        return url('/j/'.$this->public_code);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id')->withDefault(['name' => 'A former member']);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(SeminarRegistration::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(SeminarFeedback::class);
    }

    public function isP2p(): bool
    {
        return $this->delivery === 'p2p';
    }

    public function isRoom(): bool
    {
        return $this->delivery === 'room';
    }

    public function isPaid(): bool
    {
        return $this->price_cents !== null && $this->price_cents > 0;
    }

    /** "Free" or "R200.00". */
    public function priceLabel(): string
    {
        return $this->isPaid() ? self::money($this->price_cents, $this->currency ?? 'ZAR') : 'Free';
    }

    public static function money(int $cents, string $currency = 'ZAR'): string
    {
        return ($currency === 'ZAR' ? 'R' : $currency.' ').number_format($cents / 100, 2, '.', ' ');
    }

    public function endsAt()
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function hasEnded(): bool
    {
        return $this->endsAt()->isPast();
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function joinIsOpen(): bool
    {
        return $this->isPublished()
            && now()->greaterThanOrEqualTo($this->starts_at->copy()->subMinutes((int) config('seminars.join_opens_minutes_before', 15)))
            && ! $this->hasEnded();
    }

    public function scopeUpcomingPublished($q)
    {
        return $q->where('status', self::PUBLISHED)->where('starts_at', '>', now()->subMinutes((int) config('seminars.max_duration_minutes', 480)))
            ->get()->filter(fn (self $s) => ! $s->hasEnded())->sortBy('starts_at')->values();
    }
}
