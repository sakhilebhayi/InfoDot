<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\BookingProvider;
use App\Models\Team;
use App\Models\User;
use App\Services\Booking\BookingAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The people in a business who take appointments (R-63 phase 2b). A provider is a member who is added here; customers see only the label chosen
 * here, never the person's account or email. Owner, administrators and managers only. A provider with bookings is switched off, never removed.
 */
class BookingProvidersController extends Controller
{
    use GuardsBooking;

    public function __construct(private readonly BookingAudit $audit) {}

    public function index(Team $team): View
    {
        $this->manage($team);

        $providers = $team->bookingProviders()->with('user:id,name')->withCount(['appointmentTypes', 'workingHours'])->orderBy('public_name')->get();
        $taken = $providers->pluck('user_id')->all();

        return view('business.booking.providers', [
            'team' => $team,
            'providers' => $providers,
            'candidates' => $team->allUsers()->reject(fn (User $u) => in_array($u->getKey(), $taken, true))->sortBy('name')->values(),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->manage($team);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'public_name' => ['nullable', 'string', 'max:80'],
        ], ['public_name.max' => 'The name customers see can be at most 80 letters.']);

        $person = $team->allUsers()->first(fn (User $u) => $u->getKey() === (int) $data['user_id']);

        if ($person === null) {
            throw ValidationException::withMessages(['user_id' => 'Choose a person who is a member of this business.']);
        }

        if ($team->bookingProviders()->where('user_id', $person->getKey())->exists()) {
            throw ValidationException::withMessages(['user_id' => 'That person already takes appointments.']);
        }

        $label = trim((string) ($data['public_name'] ?? ''));
        $label = $label !== '' ? $label : $this->firstName($person);

        $provider = DB::transaction(function () use ($team, $person, $label) {
            $provider = $team->bookingProviders()->create(['user_id' => $person->getKey(), 'public_name' => $label, 'active' => true]);
            $this->audit->record($team, $this->user(), 'provider_added', ['provider_id' => $provider->getKey(), 'user_id' => $person->getKey(), 'public_name' => $label]);

            return $provider;
        });

        return redirect()->route('business.booking.providers', $team)->with('status', "{$provider->public_name} now takes appointments. Customers see this name.");
    }

    public function update(Request $request, Team $team, BookingProvider $provider): RedirectResponse
    {
        $this->manage($team);
        $this->belongs($team, $provider);

        $data = $request->validate(['public_name' => ['required', 'string', 'min:1', 'max:80']], ['public_name.max' => 'The name customers see can be at most 80 letters.']);
        $label = trim($data['public_name']);

        if ($label === '') {
            throw ValidationException::withMessages(['public_name' => 'Give the name customers will see.']);
        }

        if ($label !== $provider->public_name) {
            $from = $provider->public_name;
            $provider->forceFill(['public_name' => $label])->save();
            $this->audit->record($team, $this->user(), 'provider_renamed', ['provider_id' => $provider->getKey(), 'from' => $from, 'to' => $label]);
        }

        return redirect()->route('business.booking.providers', $team)->with('status', 'The name customers see is saved.');
    }

    public function toggle(Team $team, BookingProvider $provider): RedirectResponse
    {
        $this->manage($team);
        $this->belongs($team, $provider);

        $provider->forceFill(['active' => ! $provider->active])->save();
        $this->audit->record($team, $this->user(), $provider->active ? 'provider_switched_on' : 'provider_switched_off', ['provider_id' => $provider->getKey(), 'public_name' => $provider->public_name]);

        return redirect()->route('business.booking.providers', $team)->with('status', $provider->active ? "{$provider->public_name} is on." : "{$provider->public_name} is off: customers cannot choose them.");
    }

    public function destroy(Team $team, BookingProvider $provider): RedirectResponse
    {
        $this->manage($team);
        $this->belongs($team, $provider);

        if ($provider->bookings()->exists()) {
            return redirect()->route('business.booking.providers', $team)->withErrors(['provider' => "{$provider->public_name} has appointments, so they cannot be removed. Switch them off instead; the appointments stay on record."]);
        }

        DB::transaction(function () use ($team, $provider) {
            $this->audit->record($team, $this->user(), 'provider_removed', ['provider_id' => $provider->getKey(), 'public_name' => $provider->public_name]);
            $provider->delete();
        });

        return redirect()->route('business.booking.providers', $team)->with('status', "{$provider->public_name} no longer takes appointments.");
    }

    private function firstName(User $person): string
    {
        $first = trim(explode(' ', trim((string) $person->name))[0]);

        return mb_substr($first !== '' ? $first : 'Team member', 0, 80);
    }
}
