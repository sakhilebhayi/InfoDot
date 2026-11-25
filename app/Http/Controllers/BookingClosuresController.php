<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\BookingClosure;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\WorkingHour;
use App\Services\Booking\BookingSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Closures (R-63 phase 2b): holidays and blocked time, for the whole business or for one provider. Whole-business ones are for owner,
 * administrators and managers; a provider's own may also be added and removed by that provider. Shown and stored in the business's time zone.
 */
class BookingClosuresController extends Controller
{
    use GuardsBooking;

    public function __construct(private readonly BookingSchedule $schedule) {}

    public function index(Team $team): View
    {
        $this->featureOpen();
        $manages = Gate::allows('manageBookings', $team);
        $mine = $this->ownProvider($team);
        abort_unless($manages || $mine !== null, 403);

        $setting = BookingSetting::forTeam($team);
        $closures = BookingClosure::query()->where('team_id', $team->getKey())->where('ends_at', '>', now())->with('provider:id,public_name')
            ->when(! $manages, fn ($q) => $q->where('booking_provider_id', $mine?->getKey()))
            ->orderBy('starts_at')->get();

        return view('business.booking.closures', [
            'team' => $team,
            'closures' => $closures,
            'zone' => $setting->zone(),
            'manages' => $manages,
            'providers' => $manages ? $team->bookingProviders()->orderBy('public_name')->get() : collect([$mine]),
            'hasHours' => WorkingHour::query()->where('team_id', $team->getKey())->whereNull('booking_provider_id')->exists(),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->featureOpen();

        $data = $request->validate([
            'kind' => ['required', Rule::in([BookingClosure::HOLIDAY, BookingClosure::BLOCK])],
            'scope' => ['required', 'string', 'max:20'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'from' => ['nullable', 'date_format:H:i', 'required_if:kind,block'],
            'to' => ['nullable', 'date_format:H:i', 'required_if:kind,block'],
            'reason' => ['nullable', 'string', 'max:160'],
        ], [
            'ends_on.after_or_equal' => 'The last day cannot be before the first day.',
            'from.required_if' => 'Give the time the block starts.',
            'to.required_if' => 'Give the time the block ends.',
        ]);

        $provider = null;

        if ($data['scope'] === 'business') {
            Gate::authorize('manageBookings', $team);
        } else {
            $provider = ctype_digit($data['scope']) ? BookingProvider::query()->find((int) $data['scope']) : null;

            if ($provider === null || $provider->team_id !== $team->getKey()) {
                throw ValidationException::withMessages(['scope' => 'Choose the whole business or a person from this business.']);
            }

            Gate::authorize('manageProviderSchedule', [$team, $provider]);
        }

        $this->schedule->addClosure($team, $this->user(), $provider, $data);

        return redirect()->route('business.booking.closures', $team)->with('status', $data['kind'] === BookingClosure::HOLIDAY ? 'The holiday is added.' : 'The time is blocked.');
    }

    public function destroy(Team $team, BookingClosure $closure): RedirectResponse
    {
        $this->featureOpen();
        $this->belongs($team, $closure);

        if ($closure->booking_provider_id === null) {
            Gate::authorize('manageBookings', $team);
        } else {
            Gate::authorize('manageProviderSchedule', [$team, $closure->provider]);
        }

        $this->schedule->removeClosure($team, $this->user(), $closure);

        return redirect()->route('business.booking.closures', $team)->with('status', 'The closure is removed.');
    }

    /** The viewer's own provider record in this business, if they are one. */
    private function ownProvider(Team $team): ?BookingProvider
    {
        if (! $this->user()->belongsToTeam($team)) {
            return null;
        }

        return $team->bookingProviders()->where('user_id', $this->user()->getKey())->first();
    }
}
