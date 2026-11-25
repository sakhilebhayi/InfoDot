<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\WorkingHour;
use App\Services\Booking\BookingSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Weekly working hours (R-63 phase 2b): the business's own, and each provider's. The business's hours are for owner, administrators and managers;
 * a provider's own hours may also be edited by that provider. Saving replaces that scope's whole week.
 */
class BookingHoursController extends Controller
{
    use GuardsBooking;

    public function __construct(private readonly BookingSchedule $schedule) {}

    public function business(Team $team): View
    {
        $this->manage($team);

        return $this->page($team, null);
    }

    public function updateBusiness(Request $request, Team $team): RedirectResponse
    {
        $this->manage($team);
        $this->schedule->replaceWeek($team, $this->user(), null, $this->schedule->parseWeek($request->input('hours')));

        return redirect()->route('business.booking.hours', $team)->with('status', 'The business hours are saved.');
    }

    public function provider(Team $team, BookingProvider $provider): View
    {
        $this->featureOpen();
        $this->belongs($team, $provider);
        Gate::authorize('manageProviderSchedule', [$team, $provider]);

        return $this->page($team, $provider);
    }

    public function updateProvider(Request $request, Team $team, BookingProvider $provider): RedirectResponse
    {
        $this->featureOpen();
        $this->belongs($team, $provider);
        Gate::authorize('manageProviderSchedule', [$team, $provider]);
        $this->schedule->replaceWeek($team, $this->user(), $provider, $this->schedule->parseWeek($request->input('hours')));

        return redirect()->route('business.booking.providers.hours', [$team, $provider])->with('status', 'The hours are saved.');
    }

    private function page(Team $team, ?BookingProvider $provider): View
    {
        $saved = WorkingHour::query()->where('team_id', $team->getKey())->where('booking_provider_id', $provider?->getKey())
            ->orderBy('weekday')->orderBy('starts_at')->get()->groupBy('weekday');

        $week = [];

        foreach (WorkingHour::DAYS as $weekday => $name) {
            $rows = ($saved[$weekday] ?? collect())->map(fn (WorkingHour $h) => ['from' => substr((string) $h->starts_at, 0, 5), 'to' => substr((string) $h->ends_at, 0, 5)])->all();
            $week[$weekday] = $rows;
        }

        return view('business.booking.hours', [
            'team' => $team,
            'provider' => $provider,
            'week' => $week,
            'businessHasHours' => $provider === null ? $saved->isNotEmpty() : WorkingHour::query()->where('team_id', $team->getKey())->whereNull('booking_provider_id')->exists(),
            'providers' => $provider === null ? $team->bookingProviders()->withCount('workingHours')->orderBy('public_name')->get() : collect(),
            'zone' => BookingSetting::forTeam($team)->timezone,
        ]);
    }
}
