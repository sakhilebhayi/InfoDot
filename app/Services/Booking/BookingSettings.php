<?php

namespace App\Services\Booking;

use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves a business's booking settings and writes the audit trail (R-63). The only place settings change, so every change is recorded: which
 * fields moved (before and after for the short ones; only "changed" for the free-text instructions and terms) and whether bookings were switched
 * on or off.
 */
class BookingSettings
{
    /** Free-text fields: the trail says they changed, not what they said. */
    private const TEXT_FIELDS = ['instructions', 'terms'];

    /**
     * @param  array<string, mixed>  $values  validated values for BookingSetting::EDITABLE
     * @return array<string, array{from: mixed, to: mixed}|string> what changed; empty when nothing did
     */
    public function save(Team $team, User $actor, array $values): array
    {
        return DB::transaction(function () use ($team, $actor, $values) {
            $setting = BookingSetting::query()->where('team_id', $team->getKey())->lockForUpdate()->first();
            $before = $setting === null ? BookingSetting::forTeam($team) : $setting;
            $changes = [];

            foreach (BookingSetting::EDITABLE as $field) {
                if (! array_key_exists($field, $values)) {
                    continue;
                }

                $old = $before->{$field};
                $new = $values[$field];

                if ($old !== $new && ! (blank($old) && blank($new))) {
                    $changes[$field] = in_array($field, self::TEXT_FIELDS, true) ? 'changed' : ['from' => $old, 'to' => $new];
                }
            }

            if ($setting === null) {
                $setting = new BookingSetting;
                $setting->team_id = $team->getKey();
            }

            $setting->fill(array_intersect_key($values, array_flip(BookingSetting::EDITABLE)))->save();

            if ($changes !== []) {
                BookingEvent::create(['team_id' => $team->getKey(), 'actor_id' => $actor->getKey(), 'type' => 'settings_changed', 'details' => $changes]);

                if (array_key_exists('enabled', $changes)) {
                    BookingEvent::create(['team_id' => $team->getKey(), 'actor_id' => $actor->getKey(), 'type' => $values['enabled'] ? 'booking_enabled' : 'booking_disabled']);
                }
            }

            return $changes;
        });
    }
}
