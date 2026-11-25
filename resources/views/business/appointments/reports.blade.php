@php
    $words = [
        'pending' => 'Waiting for an answer', 'confirmed' => 'Confirmed', 'completed' => 'Done', 'no_show' => 'No-show', 'cancelled_by_customer' => 'Cancelled by the customer',
        'cancelled_by_business' => 'Cancelled by the business', 'rejected' => 'Rejected', 'expired' => 'Expired unanswered',
    ];
    $most = max(1, collect($report['trend']['rows'])->max(fn ($r) => max($r['made'], $r['held'])));
    $mostType = max(1, collect($report['types'])->max('count') ?? 1);
    $keep = array_filter(['provider' => $board->filter(), 'type' => $typeId]);
@endphp
<x-app-layout title="Appointments: Reports">
    @include('business.appointments._head')

    <form method="get" class="ix-filters" action="{{ route('business.booking.reports', $team) }}" aria-label="Report range">
        @if ($board->filter() !== null)<input type="hidden" name="provider" value="{{ $board->filter() }}">@endif
        <label class="ix-label" for="from">From</label>
        <input class="ix-input" id="from" name="from" type="date" value="{{ $report['from'] }}">
        <label class="ix-label" for="to">To</label>
        <input class="ix-input" id="to" name="to" type="date" value="{{ $report['to'] }}">
        <label class="ix-label" for="type">Appointment type</label>
        <select class="ix-select" id="type" name="type">
            <option value="">All types</option>
            @foreach ($types as $t)
                <option value="{{ $t->id }}" @selected($typeId === $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="ix-btn ix-btn--sm">Show</button>
        @if ($board->manages())
            <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('business.booking.reports.export', [$team, 'from' => $report['from'], 'to' => $report['to']] + $keep) }}">Download as CSV</a>
        @endif
    </form>

    @if ($clamped)
        <p class="ix-hint" role="status">The dates were adjusted to fit: reports reach two years back and cover at most {{ $maxDays }} days.</p>
    @endif
    <p class="ix-hint">Appointments that start between {{ $report['from'] }} and {{ $report['to'] }} ({{ $report['days'] }} {{ \Illuminate\Support\Str::plural('day', $report['days']) }}), in {{ str_replace('_', ' ', $zone->getName()) }} time.</p>

    @if ($report['total'] === 0)
        <section class="ix-panel" aria-label="Report"><div class="ix-panel__body"><p>No appointments start in this range yet. Pick other dates, or come back once customers have booked.</p></div></section>
    @else
        <section class="ix-panel" aria-labelledby="rp-status">
            <div class="ix-panel__body">
                <h2 id="rp-status" class="ix-h2">{{ $report['total'] }} {{ \Illuminate\Support\Str::plural('appointment', $report['total']) }}</h2>
                <dl class="ix-meta">
                    @foreach ($words as $key => $label)
                        @if ($report['counts'][$key] > 0)
                            <div><dt>{{ $label }}</dt><dd>{{ $report['counts'][$key] }}</dd></div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </section>

        <section class="ix-panel" aria-labelledby="rp-rates">
            <div class="ix-panel__body">
                <h2 id="rp-rates" class="ix-h2">Showing up and use of time</h2>
                @if ($report['no_show_rate'] !== null)
                    <p><b>{{ $report['no_show_rate'] }}%</b> of the {{ $report['tracked'] }} appointments marked done or no-show were no-shows.</p>
                @else
                    <p class="ix-hint">No-show rate appears once appointments have been marked done or as a no-show.</p>
                @endif
                @if ($report['utilisation'])
                    <p><b>{{ $report['utilisation']['percent'] }}%</b> of the time on offer was booked: {{ intdiv($report['utilisation']['booked'], 60) }} h {{ $report['utilisation']['booked'] % 60 }} min of {{ intdiv($report['utilisation']['offered'], 60) }} h {{ $report['utilisation']['offered'] % 60 }} min.</p>
                    <x-progress.bar label="Share of offered time that was booked" :value="$report['utilisation']['booked']" :max="$report['utilisation']['offered']" hide-label />
                @else
                    <p class="ix-hint">Use of time is not shown for one appointment type, for appointments with no provider, or when no working hours are set.</p>
                @endif
            </div>
        </section>

        <section class="ix-panel" aria-labelledby="rp-types">
            <div class="ix-panel__body">
                <h2 id="rp-types" class="ix-h2">Most booked</h2>
                <ul class="ix-list">
                    @foreach ($report['types'] as $t)
                        <li>{{ $t['name'] }}: <b>{{ $t['count'] }}</b> <x-progress.bar :label="$t['name']" :value="$t['count']" :max="$mostType" hide-label size="sm" /></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="ix-panel ix-panel--clip" aria-labelledby="rp-trend">
            <div class="ix-panel__body"><h2 id="rp-trend" class="ix-h2">By {{ $report['trend']['unit'] }}</h2>
                <p class="ix-hint">Requests made in the period, and appointments that fall in it (not cancelled, rejected or expired).{{ $report['trend']['unit'] === 'week' ? ' Weeks start on Monday.' : '' }}</p></div>
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>{{ $report['trend']['unit'] === 'week' ? 'Week of' : 'Day' }}</th><th>Requested</th><th>Appointments</th></tr></thead>
                    <tbody>
                        @foreach ($report['trend']['rows'] as $r)
                            @if ($r['made'] > 0 || $r['held'] > 0)
                                <tr>
                                    <td class="title">{{ $r['label'] }}</td>
                                    <td data-label="Requested">{{ $r['made'] }}</td>
                                    <td data-label="Appointments">{{ $r['held'] }} <x-progress.bar :label="'Appointments on '.$r['label']" :value="$r['held']" :max="$most" hide-label size="sm" /></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-app-layout>
