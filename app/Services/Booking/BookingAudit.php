<?php

namespace App\Services\Booking;

use App\Models\BookingEvent;
use App\Models\Team;
use App\Models\User;

/**
 * Writes the booking audit trail for the setup screens (R-63): which record changed and which fields moved. Never a customer's name, contact
 * details or reason for an appointment; the longer free-text fields are recorded as "changed", not as what they said.
 */
class BookingAudit
{
    /** @param  array<string, mixed>  $details */
    public function record(Team $team, User $actor, string $type, array $details = []): void
    {
        BookingEvent::create(['team_id' => $team->getKey(), 'actor_id' => $actor->getKey(), 'type' => $type, 'details' => $details === [] ? null : $details]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $textFields  recorded only as "changed"
     * @return array<string, array{from: mixed, to: mixed}|string>
     */
    public function changes(array $before, array $after, array $textFields = []): array
    {
        $out = [];

        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;

            if ($old === $new || (blank($old) && blank($new))) {
                continue;
            }

            $out[$field] = in_array($field, $textFields, true) ? 'changed' : ['from' => $old, 'to' => $new];
        }

        return $out;
    }
}
