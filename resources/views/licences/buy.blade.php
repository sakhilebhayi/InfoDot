@php
    $fm = fn (?int $minor, ?string $c) => $minor === null ? null : \App\Services\Licensing\SeatLedger::formatMoney($minor, $c);
    $canBuy = $isOwner && $connected && $price;
    $max = max(1, (int) config('licensing.max_online_users', 100) - 1);
@endphp
<x-app-layout title="Licences for {{ $team->name }}">
    <div class="ix-plat ix-plat--confirm">
        <h1 class="ix-h1">Add people to {{ $team->name }}</h1>
        <p class="ix-lede">Your free licence covers you. Each extra person needs a licence.</p>

        @if (session('licence_error'))
            <p class="ix-alert" role="alert" data-licence-error>{{ session('licence_error') }}</p>
        @endif

        @include('licences._why', ['team' => $team, 'price' => $price, 'bands' => $bands])

        @if ($seats['available'] > 0)
            <p class="ix-hint" data-have-free>You already have {{ $seats['available'] }} {{ $seats['available'] === 1 ? 'licence' : 'licences' }} free. <a href="{{ route('teams.show', $team) }}#members">Invite someone</a> without buying anything.</p>
        @endif

        @if (! $isOwner)
            <p class="ix-hint" data-owner-only>Only the owner of {{ $team->name }}{{ $team->owner ? ', '.$team->owner->name.',' : '' }} can buy licences. Ask them to buy one, and you can invite the person once it appears.</p>
        @elseif (! $price)
            <p class="ix-hint" data-no-price>Licences cannot be bought until a price is set. Nothing has been charged and nothing is held for you.</p>
        @elseif (! $connected)
            <p class="ix-hint" data-not-connected>Payments are not connected yet, so licences cannot be bought just now. Nothing has been charged.</p>
        @endif

        @if ($canBuy)
            <form method="POST" action="{{ route('licences.checkout', $team->uuid) }}" data-buy-form>
                @csrf
                <div class="ix-field">
                    <label class="ix-label" for="licence-quantity">How many licences</label>
                    <input id="licence-quantity" class="ix-input" type="number" name="quantity" min="1" max="{{ $max }}" step="1" inputmode="numeric" value="{{ $quantity }}" required style="max-width: 160px;">
                    <p class="ix-hint">Buying several at once is one purchase with one renewal date. Up to {{ $max }} online.</p>
                </div>

                <fieldset class="ix-choice" style="margin-top: 20px;">
                    <legend class="ix-label">How you pay</legend>
                    @foreach (['month' => 'Monthly', 'year' => 'Yearly'] as $value => $label)
                        <label class="ix-choice__opt">
                            <input type="radio" name="interval" value="{{ $value }}" @checked($interval === $value)>
                            <span>
                                <strong>{{ $label }}@if ($value === 'year' && $saving), you save {{ $fm($saving, $price['currency']) }}@endif</strong>
                                <span class="ix-hint">
                                    @if ($quotes[$value])
                                        {{ $fm($quotes[$value]['total_minor'], $quotes[$value]['currency']) }} for {{ $quantity }} {{ $quantity === 1 ? 'licence' : 'licences' }}, excluding VAT, {{ $value === 'year' ? 'each year' : 'each month' }}
                                    @else
                                        Not available for this quantity
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @if ($refusal)
                    <p class="ix-hint" data-refusal>{{ $refusal }}</p>
                @endif

                <div class="ix-actions" style="margin-top: 20px;">
                    <button type="submit" class="ix-btn ix-btn--primary" data-pay>Continue to payment</button>
                    <button type="submit" class="ix-btn" formmethod="get" formaction="{{ route('licences.buy', $team->uuid) }}" data-update-total>Update the total</button>
                    <a class="ix-btn ix-btn--ghost" href="{{ route('teams.show', $team) }}#seats">Back to licences</a>
                </div>
                <p class="ix-hint">You pay on the payment service's own page. Prices exclude VAT; that page shows exactly what you will be charged. Nobody gets a licence until the payment is confirmed, and nothing renews without you being able to see it here.</p>
            </form>
        @else
            <div class="ix-actions"><a class="ix-btn ix-btn--ghost" href="{{ route('teams.show', $team) }}#seats">Back to licences</a></div>
        @endif
    </div>
</x-app-layout>
