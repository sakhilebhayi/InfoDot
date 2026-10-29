@php
    $money = $seats['price']
        ? number_format($seats['price']['amount_minor_units'] / 100, 2).' '.$seats['price']['currency']
        : null;
    $labels = ['invited' => 'Invitation sent', 'joined' => 'Joined', 'left' => 'Left or removed', 'blocked' => 'Invitation blocked: no seat free', 'capacity_changed' => 'Seats changed'];
@endphp
<div class="ix-card" data-seats>
    <h2 class="ix-h2">{{ __('Seats') }}</h2>
    <p class="ix-lede">{{ __('A seat is one licensed person in :team. The first :n are free.', ['team' => $team->name, 'n' => $seats['included']]) }}</p>

    <dl class="ix-kv">
        <div><dt>{{ __('Seats in use') }}</dt><dd>{{ $seats['people'] }} {{ __('of') }} {{ $seats['capacity'] }}</dd></div>
        <div><dt>{{ __('Held by unanswered invitations') }}</dt><dd>{{ $seats['reserved'] }}</dd></div>
        <div><dt>{{ __('Free seats left') }}</dt><dd>{{ $seats['available'] }}</dd></div>
        <div><dt>{{ __('Paid seats') }}</dt><dd>{{ $seats['purchased'] }}</dd></div>
    </dl>

    @if ($seats['over_by'] > 0)
        <p class="ix-hint">{{ __('This company has :n more people than seats. Nobody is removed; you just cannot add anyone until there is room.', ['n' => $seats['over_by']]) }}</p>
    @endif

    @if ($seats['available'] === 0)
        <p class="ix-hint">
            @if ($seats['can_buy'])
                {{ __('Add a seat to invite another person (:price per seat).', ['price' => $money]) }}
            @else
                {{ __('Seats beyond the free :n are paid, and paid seats are not available yet. Nothing has been charged. You can cancel an unanswered invitation to free its seat.', ['n' => $seats['included']]) }}
            @endif
        </p>
    @endif

    @if ($history->isNotEmpty())
        <h3 class="ix-h3">{{ __('Recent changes') }}</h3>
        <ul class="ix-list">
            @foreach ($history as $event)
                <li>{{ $labels[$event->type] ?? $event->type }}@if($event->subject) — {{ $event->subject }}@endif <span class="ix-ref">{{ $event->created_at->diffForHumans() }}</span></li>
            @endforeach
        </ul>
    @endif
</div>
