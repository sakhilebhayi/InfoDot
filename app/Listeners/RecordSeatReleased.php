<?php

namespace App\Listeners;

use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\LicenceLifecycle;
use App\Services\Licensing\SeatLedger;
use Laravel\Jetstream\Events\TeamMemberRemoved;

/** Writes the seat audit entry when someone leaves or is removed. The person's account and content are untouched: only the membership ends. */
class RecordSeatReleased
{
    public function handle(TeamMemberRemoved $event): void
    {
        app(SeatLedger::class)->record($event->team, auth()->user(), 'left', $event->user->email ?? null);

        // Their licence record goes with the membership; leaving may also make room for someone who had lapsed.
        app(LicenceAssignments::class)->recordLeft($event->team, $event->user);
        app(LicenceLifecycle::class)->rebalance($event->team);
    }
}
