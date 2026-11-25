<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\Booking;
use App\Services\Booking\BookingFeature;
use App\Services\Booking\BookingRefused;
use App\Services\Booking\Bookings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A customer's own appointments (R-63 phase 3). A booking is found by its reference AND the signed-in person, so a reference that belongs to someone
 * else is a 404 like one that does not exist: references cannot be probed. Nothing of the business's internal notes is ever loaded into these views.
 */
class MyBookingsController extends Controller
{
    use GuardsBooking;

    public function __construct(private readonly Bookings $bookings) {}

    public function index(): View
    {
        abort_unless(app(BookingFeature::class)->available(), 404);
        $user = $this->user();

        $all = Booking::query()->where('customer_user_id', $user->getKey())
            ->with(['team:id,name,uuid', 'appointmentType:id,name,team_id', 'team.bookingSetting:id,team_id,timezone'])
            ->orderBy('starts_at')->limit(200)->get();

        [$upcoming, $past] = $all->partition(fn (Booking $b) => $b->ends_at->gt(now()) && in_array($b->status, Booking::HOLDING, true));

        return view('my-bookings.index', ['upcoming' => $upcoming->values(), 'past' => $past->sortByDesc('starts_at')->values(), 'bookings' => $this->bookings]);
    }

    public function show(string $reference): View
    {
        return view('my-bookings.show', ['booking' => $this->mine($reference), 'bookings' => $this->bookings]);
    }

    public function cancel(string $reference): RedirectResponse
    {
        $booking = $this->mine($reference);

        try {
            $this->bookings->cancelByCustomer($booking, $this->user());
        } catch (BookingRefused $refused) {
            return redirect()->route('my-bookings.show', $booking->reference)->withErrors(['booking' => $refused->getMessage()]);
        }

        return redirect()->route('my-bookings.show', $booking->reference)->with('status', 'cancelled');
    }

    public function reschedule(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->mine($reference);
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:300']], [
            'note.required' => 'Tell '.$booking->team->name.' when would suit you, or why you need to move.',
            'note.min' => 'Please write a little more, at least 3 characters.',
            'note.max' => 'Please keep it to 300 characters.',
        ]);

        try {
            $this->bookings->requestReschedule($booking, $this->user(), $data['note']);
        } catch (BookingRefused $refused) {
            return redirect()->route('my-bookings.show', $booking->reference)->withErrors(['booking' => $refused->getMessage()]);
        }

        return redirect()->route('my-bookings.show', $booking->reference)->with('status', 'reschedule_requested');
    }

    private function mine(string $reference): Booking
    {
        abort_unless(app(BookingFeature::class)->available(), 404);

        return Booking::query()->where('reference', $reference)->where('customer_user_id', $this->user()->getKey())
            ->with(['team', 'appointmentType', 'team.bookingSetting'])->firstOrFail();
    }
}
