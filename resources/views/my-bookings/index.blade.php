<x-app-layout title="My bookings">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">My bookings</h1>
            <p class="ix-lede">Your appointments with businesses on InfoDot. Only you can see this page.</p>
        </div>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif

    @foreach ([['Upcoming', $upcoming, 'No upcoming appointments.'], ['Earlier', $past, 'Nothing earlier.']] as [$heading, $rows, $empty])
        <section class="ix-panel ix-panel--clip" aria-labelledby="h-{{ $loop->index }}">
            <div class="ix-panel__body"><h2 class="ix-h2" id="h-{{ $loop->index }}">{{ $heading }}</h2></div>
            @if ($rows->isEmpty())
                <div class="ix-panel__body"><p>{{ $empty }}</p></div>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table">
                        <thead><tr><th>Reference</th><th>Business</th><th>Appointment</th><th>When</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($rows as $b)
                                @php($zone = ($b->team->bookingSetting ?? \App\Models\BookingSetting::forTeam($b->team))->zone())
                                <tr>
                                    <td class="title" data-label="Reference"><a class="title" href="{{ route('my-bookings.show', $b->reference) }}">{{ $b->reference }}</a></td>
                                    <td data-label="Business">{{ $b->team->name }}</td>
                                    <td data-label="Appointment">{{ $b->appointmentType->name }}</td>
                                    <td data-label="When"><x-booking-zone :zone="$zone->getName()" class="ix-inline"><time datetime="{{ $b->starts_at->utc()->format('Y-m-d\TH:i:s\Z') }}" data-show="full">{{ $b->starts_at->setTimezone($zone)->format('D j M Y, H:i') }}</time> <span class="ix-hint">{{ $zone->getName() }}</span></x-booking-zone></td>
                                    <td data-label="Status">@include('my-bookings._status', ['booking' => $b])</td>
                                    <td>
                                        @php($why = $bookings->whyNotCancel($b, auth()->user()))
                                        @if ($why === null)
                                            <form method="post" action="{{ route('my-bookings.cancel', $b->reference) }}">@csrf<button type="submit" class="ix-btn ix-btn--sm ix-btn--danger">Cancel</button></form>
                                        @else
                                            <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('my-bookings.show', $b->reference) }}">Details</a>
                                            @if ($rows === $upcoming)<p class="ix-hint">{{ $why }}</p>@endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach

    <p class="ix-hint">Want a copy of everything appointment booking holds about you? <a href="{{ route('my-bookings.export') }}">Download my booking data</a>. Businesses remove your details from appointments {{ config('booking.retention_months') }} months after they end.</p>
</x-app-layout>
