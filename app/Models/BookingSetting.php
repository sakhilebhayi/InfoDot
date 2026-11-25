<?php

namespace App\Models;

use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one business takes appointments (R-63). One row per business; a business with no row takes no bookings and `forTeam()` hands back the
 * defaults, unsaved, so the settings screen can show them without creating anything.
 *
 * @property int $id
 * @property int $team_id
 * @property bool $enabled
 * @property bool $calendar_published
 * @property string $approval_mode
 * @property string $timezone
 * @property int $min_notice_minutes
 * @property int $horizon_days
 * @property int $cancel_cutoff_hours
 * @property string|null $instructions
 * @property string|null $terms
 */
class BookingSetting extends Model
{
    public const AUTO = 'auto';

    public const APPROVAL = 'approval';

    public const APPROVAL_MODES = [self::AUTO, self::APPROVAL];

    public const DEFAULT_TIMEZONE = 'Africa/Johannesburg';

    /** The fields a manager may change from the settings screen, in the order they are shown and audited. */
    public const EDITABLE = ['enabled', 'calendar_published', 'approval_mode', 'timezone', 'min_notice_minutes', 'horizon_days', 'cancel_cutoff_hours', 'instructions', 'terms'];

    protected $fillable = self::EDITABLE;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'calendar_published' => 'boolean',
            'min_notice_minutes' => 'integer',
            'horizon_days' => 'integer',
            'cancel_cutoff_hours' => 'integer',
        ];
    }

    /** The business's saved settings, or its defaults (not saved) when it has none yet. */
    public static function forTeam(Team $team): self
    {
        return self::query()->where('team_id', $team->getKey())->first()
            ?? (new self)->forceFill([
                'team_id' => $team->getKey(),
                'enabled' => false,
                'calendar_published' => true,
                'approval_mode' => self::APPROVAL,
                'timezone' => self::DEFAULT_TIMEZONE,
                'min_notice_minutes' => 120,
                'horizon_days' => 60,
                'cancel_cutoff_hours' => 24,
            ]);
    }

    /** Whether this business is taking new appointment requests right now (the feature switch is checked separately). */
    public function isOpen(): bool
    {
        return $this->exists && $this->enabled;
    }

    /** The time zone every date and time of this business is worked out in. */
    public function zone(): DateTimeZone
    {
        return in_array($this->timezone, DateTimeZone::listIdentifiers(), true) ? new DateTimeZone($this->timezone) : new DateTimeZone(self::DEFAULT_TIMEZONE);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
