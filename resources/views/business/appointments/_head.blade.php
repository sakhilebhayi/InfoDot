@include('business.booking._nav', ['team' => $team, 'active' => 'appointments'])

<div class="ix-pagehead">
    <div>
        <h1 class="ix-h1">Appointments</h1>
        <p class="ix-lede">{{ $board->manages() ? 'Every appointment of '.$team->name.'.' : 'Your appointments with '.$team->name.'.' }} Times are in {{ str_replace('_', ' ', $zone->getName()) }} time.</p>
    </div>
</div>

@php
    $keep = array_filter(['provider' => $board->filter()]);
    $subtabs = [
        'requests' => ['Requests'.($pending > 0 ? ' ('.$pending.')' : ''), route('business.appointments', [$team] + $keep)],
        'upcoming' => ['Upcoming', route('business.appointments.upcoming', [$team] + $keep)],
        'calendar' => ['Calendar', route('business.appointments.calendar', [$team] + $keep)],
        'past' => ['Past and closed', route('business.appointments.past', [$team] + $keep)],
        'reports' => ['Reports', route('business.booking.reports', [$team] + $keep)],
    ];
@endphp
<nav class="ix-mod__tabs" aria-label="Appointment views">
    @foreach ($subtabs as $key => [$label, $url])
        <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if (session('status'))
    <p class="ix-alert" role="status">{{ session('status') }}</p>
@endif

@if ($board->manages() && $providers->isNotEmpty())
    <form method="get" class="ix-filters" x-data x-on:change="$el.submit()" action="{{ url()->current() }}" aria-label="Filter by provider">
        @foreach (request()->except(['provider', 'page']) as $k => $v)
            @if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label class="ix-sr" for="provider">Filter by provider</label>
        <select class="ix-select" id="provider" name="provider">
            <option value="">All providers</option>
            <option value="unassigned" @selected($board->filter() === 'unassigned')>Not assigned to anyone</option>
            @foreach ($providers as $p)
                <option value="{{ $p->id }}" @selected($board->filter() === (string) $p->id)>{{ $p->public_name }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="ix-btn ix-btn--sm">Filter</button></noscript>
    </form>
@endif
