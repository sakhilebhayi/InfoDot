<?php

namespace App\Http\Controllers;

use App\Models\BookingSetting;
use App\Models\Team;
use App\Services\Booking\BookingFeature;
use App\Services\Booking\BookingSettings;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A business's appointment booking settings (R-63 phase 1): switch bookings on or off, choose automatic confirmation or approval, the business's
 * time zone, how much notice and how far ahead, the cancellation cut-off, and the instructions and terms customers see. Owner, administrators and
 * managers only. While the `bookings` feature is not available the screen does not exist (404), so nothing is offered before it is open.
 */
class BookingSettingsController extends Controller
{
    public function __construct(private readonly BookingFeature $feature, private readonly BookingSettings $settings) {}

    public function show(Request $request, Team $team): View
    {
        $this->guard($team);

        return view('business.booking-settings', [
            'team' => $team,
            'setting' => BookingSetting::forTeam($team),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->guard($team);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'calendar_published' => ['sometimes', 'boolean'],
            'approval_mode' => ['required', Rule::in(BookingSetting::APPROVAL_MODES)],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancel_cutoff_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'instructions' => ['nullable', 'string', 'max:1500'],
            'terms' => ['nullable', 'string', 'max:4000'],
        ], [
            'timezone.in' => 'Choose a time zone from the list.',
            'min_notice_hours.max' => 'Notice can be at most 14 days (336 hours).',
            'horizon_days.max' => 'Customers can book at most a year ahead.',
        ]);

        // An unticked box is absent from the form, so absent means off.
        $this->settings->save($team, $request->user(), [
            'enabled' => $request->boolean('enabled'),
            'calendar_published' => $request->boolean('calendar_published'),
            'approval_mode' => $data['approval_mode'],
            'timezone' => $data['timezone'],
            'min_notice_minutes' => $data['min_notice_hours'] * 60,
            'horizon_days' => $data['horizon_days'],
            'cancel_cutoff_hours' => $data['cancel_cutoff_hours'],
            'instructions' => filled($data['instructions'] ?? null) ? trim($data['instructions']) : null,
            'terms' => filled($data['terms'] ?? null) ? trim($data['terms']) : null,
        ]);

        return redirect()->route('business.booking.settings', $team)->with('status', 'Booking settings saved.');
    }

    private function guard(Team $team): void
    {
        abort_unless($this->feature->available(), 404);
        Gate::authorize('manageBookings', $team);
    }
}
