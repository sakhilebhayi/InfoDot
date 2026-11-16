@php
    // Prices come from configuration only (SeatLedger::price); with none configured nothing about price is shown.
    $money = $seats['price'] ? \App\Services\Licensing\SeatLedger::formatMoney($seats['price']['amount_minor_units'], $seats['price']['currency']) : null;
    $moneyYear = $seats['price'] ? \App\Services\Licensing\SeatLedger::formatMoney($seats['price']['annual_amount_minor_units'], $seats['price']['currency']) : null;
    $priceText = $money ? $money.' '.__('per person per month').($moneyYear ? ', '.$moneyYear.' '.__('per person per year') : '').', '.__('excluding VAT') : null;
    $labels = ['invited' => 'Invitation sent', 'joined' => 'Joined', 'left' => 'Left or removed', 'blocked' => 'Invitation blocked: no licence free', 'capacity_changed' => 'Licences changed', 'ownership_transferred' => 'Ownership transferred to', 'grandfathered' => 'Current people kept free for a transition period', 'grandfather_rolled_back' => 'Transition period undone'];
@endphp
<x-jet-action-section>
    <x-slot name="title">{{ __('Licences') }}</x-slot>
    <x-slot name="description">{{ $seats['included'] === 1
        ? __('A licence is one person in :team. The owner holds the one free licence; every other person needs a paid licence.', ['team' => $team->name])
        : __('A licence is one person in :team. The first :n are free; every other person needs a paid licence.', ['team' => $team->name, 'n' => $seats['included']]) }}</x-slot>
    <x-slot name="content">
        <div data-seats>
            <table class="ix-table">
                <tbody>
                    <tr><th scope="row">{{ __('Licences in use') }}</th><td>{{ $seats['people'] }} {{ __('of') }} {{ $seats['capacity'] }}</td></tr>
                    <tr><th scope="row">{{ __('Held by unanswered invitations') }}</th><td>{{ $seats['reserved'] }}</td></tr>
                    <tr><th scope="row">{{ __('Licences available') }}</th><td>{{ $seats['available'] }}</td></tr>
                    <tr><th scope="row">{{ __('Paid licences') }}</th><td>{{ $seats['purchased'] }}</td></tr>
                </tbody>
            </table>

            @if ($seats['grandfathered'])
                <p class="ix-hint">{{ __('This company keeps the people it has now free until :date. Nobody is removed. Anyone added beyond them needs a paid licence.', ['date' => $seats['grandfathered_until']->format('j F Y')]) }}</p>
            @endif

            @if ($seats['over_by'] > 0)
                <p class="ix-hint">{{ __('This company has :n more people than licences. Nobody is removed; you just cannot add anyone until there is room.', ['n' => $seats['over_by']]) }}</p>
            @endif

            @if ($priceText)
                <p class="ix-hint">{{ __('An extra licence costs :price.', ['price' => $priceText]) }}</p>
            @endif

            @if ($seats['can_buy'] && Gate::check('changeLicence', $team))
                <p style="margin-top: 16px;">
                    <a class="ix-btn ix-btn--primary" href="{{ \App\Support\EcosystemHandoff::launchUrl(auth()->user(), 'finance', '/seats') }}">{{ __('Add or change licences in Dot.Finance') }}</a>
                </p>
                <p class="ix-hint">{{ __('Licences beyond the free :n are bought in Dot.Finance. An extra licence counts only after the payment is confirmed.', ['n' => $seats['included']]) }}</p>
            @endif

            @if ($seats['available'] === 0)
                <p class="ix-hint">
                    @if ($seats['can_buy'])
                        {{ $priceText ? __('Add a licence to invite another person (:price).', ['price' => $priceText]) : __('Add a licence in Dot.Finance to invite another person.') }}
                    @else
                        {{ __('Every person beyond the free :n needs a paid licence, and paid licences are not available yet. Nothing has been charged. You can cancel an unanswered invitation to free its licence.', ['n' => $seats['included']]) }}
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
