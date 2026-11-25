<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use App\Models\User;
use App\Services\Booking\BookingFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The checks every booking setup screen starts with (R-63): the screen does not exist (404) until the `bookings` feature is available, a record
 * from another business is a 404 (never a 403, so nothing about other businesses can be probed), and only then the person's permission.
 */
trait GuardsBooking
{
    /** The feature must be open. */
    protected function featureOpen(): void
    {
        abort_unless(app(BookingFeature::class)->available(), 404);
    }

    /** The feature is open and the person manages this business's bookings. */
    protected function manage(Team $team): void
    {
        $this->featureOpen();
        Gate::authorize('manageBookings', $team);
    }

    /** The record belongs to this business, or it does not exist as far as this URL is concerned. */
    protected function belongs(Team $team, Model $record): void
    {
        abort_unless((int) $record->getAttribute('team_id') === (int) $team->getKey(), 404);
    }

    protected function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
