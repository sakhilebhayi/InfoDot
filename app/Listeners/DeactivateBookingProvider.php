<?php

namespace App\Listeners;

use App\Models\BookingProvider;
use Laravel\Jetstream\Events\TeamMemberRemoved;

/**
 * A person who leaves or is removed from a business stops being one of its booking providers (R-63). Their record and the bookings assigned to them are
 * kept (the business reassigns them), but they no longer offer appointments and no longer reach any booking screen.
 */
class DeactivateBookingProvider
{
    public function handle(TeamMemberRemoved $event): void
    {
        BookingProvider::query()->where('team_id', $event->team->getKey())->where('user_id', $event->user->getKey())->update(['active' => false]);
    }
}
