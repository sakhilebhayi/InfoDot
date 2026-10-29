@php
    $money = $seats['price']
        ? number_format($seats['price']['amount_minor_units'] / 100, 2).' '.$seats['price']['currency']
        : null;
    $labels = ['invited' => 'Invitation sent', 'joined' => 'Joined', 'left' => 'Left or removed', 'blocked' => 'Invitation blocked: no seat free', 'capacity_changed' => 'Seats changed'];
@endphp
<x-jet-action-section>
    <x-slot name="title">{{ __('Seats') }}</x-slot>
    <x-slot name="description">{{ __('A seat is one licensed person in :team. The first :n are free.', ['team' => $team->name, 'n' => $seats['included']]) }}</x-slot>
    <x-slot name="content">
        <div data-seats>
            <table class="ix-table">
                <tbody>
                    <tr><th scope="row">{{ __('Seats in use') }}</th><td>{{ $seats['people'] }} {{ __('of') }} {{ $seats['capacity'] }}</td></tr>
                    <tr><th scope="row">{{ __('Held by unanswered invitations') }}</th><td>{{ $seats['reserved'] }}</td></tr>
                    <tr><th scope="row">{{ __('Free seats left') }}</th><td>{{ $seats['available'] }}</td></tr>
                    <tr><th scope="row">{{ __('Paid seats') }}</th><td>{{ $seats['purchased'] }}</td></tr>
                </tbody>
            </table>

            @if ($seats['over_by'] > 0)
                <p class="ix-hint">{{ __('This company has :n more people than seats. Nobody is removed; you just cannot add anyone until there is room.', ['n' => $seats['over_by']]) }}</p>
            @endif

            @if ($seats['can_buy'] && Gate::check('changeLicence', $team))
                <p style="margin-top: 16px;">
                    <a class="ix-btn ix-btn--primary" href="{{ \App\Support\EcosystemHandoff::launchUrl(auth()->user(), 'billing', '/seats') }}">{{ __('Add or change seats in Dot.Billing') }}</a>
                </p>
                <p class="ix-hint">{{ __('Seats beyond the free :n are bought in Dot.Billing. Extra seats count only after the payment is confirmed.', ['n' => $seats['included']]) }}</p>
            @endif

            @if ($seats['available'] === 0)
                <p class="ix-hint">
                    @if ($seats['can_buy'])
                        {{ $money ? __('Add a seat to invite another person (:price per seat).', ['price' => $money]) : __('Add a seat in Dot.Billing to invite another person.') }}
                    @else
                        {{ __('Seats beyond the free :n are paid, and paid seats are not available yet. Nothing has been charged. You can cancel an unanswered invitation to free its seat.', ['n' => $seats['included']]) }}
                    @endif
                </p>
            @endif

            @if ($history->isNotEmpty())
                <h3 class="ix-label" style="margin-top: 20px;">{{ __('Recent changes') }}</h3>
                <ul class="ix-list">
                    @foreach ($history as $event)
                        <li style="padding: 10px 14px;">{{ $labels[$event->type] ?? $event->type }}@if($event->subject) — {{ $event->subject }}@endif <span class="ix-ref">{{ $event->created_at->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-slot>
</x-jet-action-section>
