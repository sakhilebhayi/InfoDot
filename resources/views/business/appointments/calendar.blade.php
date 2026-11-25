@php
    $heading = match ($view) {
        'day' => $date->format('l j F Y'),
        'week' => $date->startOfWeek()->format('j M').' to '.$date->startOfWeek()->addDays(6)->format('j M Y'),
        default => $date->format('F Y'),
    };
    $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
@endphp
<x-app-layout title="Appointments: Calendar">
    @include('business.appointments._head')

    <section class="ix-panel" aria-labelledby="cal-h">
        <div class="ix-panel__head"><h2 id="cal-h">{{ $heading }}</h2></div>
        <div class="ix-panel__body">

            <nav class="ix-actions" aria-label="Calendar view">
                @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
                    <a class="ix-btn ix-btn--sm @if ($view !== $key) ix-btn--ghost @endif" href="{{ $url($key, $date) }}" @if ($view === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <nav class="ix-actions" aria-label="Move through the calendar">
                @if ($prevUrl)<a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ $prevUrl }}" rel="prev">Previous</a>@endif
                <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ $todayUrl }}">Today</a>
                @if ($nextUrl)<a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ $nextUrl }}" rel="next">Next</a>@endif
                <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ $closedUrl }}">{{ $showClosed ? 'Hide closed appointments' : 'Show closed appointments too' }}</a>
            </nav>
            <p class="ix-hint">Appointments that hold time (waiting and confirmed){{ $showClosed ? ', and closed ones' : '' }}. Holidays and blocked time are marked, with the note only your team sees.</p>
        </div>
    </section>

    @if ($view === 'month')
        @php $cells = array_merge(array_fill(0, $leading, null), array_values($days)); while (count($cells) % 7 !== 0) { $cells[] = null; } @endphp
        <section class="ix-panel ix-panel--clip" aria-label="{{ $heading }}">
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr>@foreach ($dayNames as $n)<th scope="col">{{ substr($n, 0, 3) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (array_chunk($cells, 7) as $week)
                            <tr>
                                @foreach ($week as $i => $cell)
                                    @if ($cell === null)
                                        <td></td>
                                    @else
                                        @php $n = $cell['bookings']->count(); @endphp
                                        <td data-label="{{ $dayNames[$i] }}">
                                            <a href="{{ $url('day', $cell['date']) }}" @if ($cell['date']->equalTo($today)) aria-current="date" @endif><b>{{ $cell['date']->format('j') }}</b>@if ($n > 0) <span class="ix-chip ix-chip--info">{{ $n }} {{ $n === 1 ? 'appointment' : 'appointments' }}</span>@endif</a>
                                            @foreach ($cell['closures'] as $c)<span class="ix-hint">{{ $c['label'] }}</span>@endforeach
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        @foreach ($days as $key => $day)
            <section class="ix-panel ix-panel--clip" aria-labelledby="d-{{ $key }}">
                <div class="ix-panel__head"><h3 id="d-{{ $key }}" class="ix-h2"><a href="{{ $url('day', $day['date']) }}">{{ $day['date']->format('l j M') }}</a>@if ($day['date']->equalTo($today)) <span class="ix-chip ix-chip--info">Today</span>@endif</h3></div>
                @foreach ($day['closures'] as $c)
                    <p class="ix-alert" role="note">{{ $c['label'] }} ({{ $c['who'] }})@if ($c['reason']): {{ $c['reason'] }}@endif</p>
                @endforeach
                @if ($day['bookings']->isEmpty())
                    <div class="ix-panel__body"><p class="ix-hint">No appointments.</p></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table">
                            <thead><tr><th>Time</th><th>Customer</th><th>Appointment</th><th>Who</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($day['bookings'] as $b)
                                    <tr>
                                        <td class="title"><a href="{{ route('business.appointments.show', [$team, $b]) }}">{{ $b->starts_at->copy()->setTimezone($zone)->format('H:i') }} to {{ $b->ends_at->copy()->setTimezone($zone)->format('H:i') }}</a></td>
                                        <td data-label="Customer">{{ $b->customer_name }}</td>
                                        <td data-label="Appointment">{{ $b->appointmentType->name }}</td>
                                        <td data-label="Who" class="muted">{{ $b->provider?->public_name ?? 'Not assigned' }}</td>
                                        <td data-label="Status">@include('business.appointments._status', ['booking' => $b])@if ($b->reschedule_requested_at && in_array($b->status, \App\Models\Booking::HOLDING, true)) <span class="ix-chip ix-chip--info">Asked to move</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    @endif
</x-app-layout>
