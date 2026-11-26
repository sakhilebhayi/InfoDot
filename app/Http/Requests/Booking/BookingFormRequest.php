<?php

namespace App\Http\Requests\Booking;

use App\Models\Team;
use App\Services\Booking\BookingFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * R-72 (Q-20): a form on a booking setup screen. The checks keep those screens' order (GuardsBooking): the feature closed is 404, a
 * record from another business is 404, then the person must manage this business's bookings (403), and only then is the form validated.
 */
abstract class BookingFormRequest extends FormRequest
{
    /** Route parameters that must belong to the business in the URL. */
    protected array $owned = [];

    public function authorize(): bool
    {
        abort_unless(app(BookingFeature::class)->available(), 404);

        $team = $this->team();
        foreach ($this->owned as $parameter) {
            $record = $this->route($parameter);
            abort_if($record instanceof Model && (int) $record->getAttribute('team_id') !== (int) $team->getKey(), 404);
        }

        return (bool) $this->user()?->can('manageBookings', $team);
    }

    protected function team(): Team
    {
        $team = $this->route('team');
        abort_unless($team instanceof Team, 404);

        return $team;
    }
}
