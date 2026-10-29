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

    protected $fillable = ['host_id', 'topic_id', 'title', 'summary', 'description', 'starts_at', 'duration_minutes', 'capacity', 'join_url', 'status', 'cancelled_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'cancelled_at' => 'datetime', 'duration_minutes' => 'integer', 'capacity' => 'integer'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
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
