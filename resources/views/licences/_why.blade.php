@php
    // Every figure here is read from configuration (SeatLedger::price); with none set the page says so and offers nothing to buy.
    $fm = fn (?int $minor) => $minor === null ? null : \App\Services\Licensing\SeatLedger::formatMoney($minor, $price['currency'] ?? null);
    $month = $price ? $fm($price['amount_minor_units']) : null;
    $year = $price && $price['annual_amount_minor_units'] ? $fm($price['annual_amount_minor_units']) : null;
    $yearSaving = $price && $price['annual_amount_minor_units'] ? max(0, $price['amount_minor_units'] * 12 - $price['annual_amount_minor_units']) : 0;
@endphp
<ul class="ix-plat__terms" data-licence-why>
    <li><strong>Why a licence is needed.</strong> {{ $team->name }} has one free licence, held by its owner. Anyone else who works in {{ $team->name }} needs a licence of their own before they can join.</li>
    <li><strong>What a licence includes.</strong> One person, one company. It lets them sign in to InfoDot and to every Dot platform {{ $team->name }} has switched on, with no extra charge per platform.</li>
    <li>
        <strong>What it costs.</strong>
        @if ($month)
            {{ $month }} per person per month{{ $year ? ', or '.$year.' per person per year'.($yearSaving > 0 ? ' (you save '.$fm($yearSaving).' a year)' : '') : '' }}, excluding VAT.
            @if (! empty($bands)) {{ $bands }} @endif
        @else
            No price has been set yet, so licences cannot be bought and nothing can be charged.
        @endif
    </li>
    <li><strong>How to buy more.</strong> Choose how many you need and whether to pay monthly or yearly. Each purchase has its own renewal date, and you can buy again at any time. The licences appear here once the payment service has confirmed your payment, and then you can invite.</li>
</ul>
