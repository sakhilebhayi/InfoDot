<x-app-layout>
    @php($tz = config('app.timezone'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $taxInvoice ? 'Tax invoice' : 'Receipt' }}</h1>
            <p class="ix-lede">{{ $seminar->title }}</p>
        </div>
        <button class="ix-btn" type="button" onclick="window.print()">Print</button>
    </div>
    <table class="ix-table" style="max-width: 640px;">
        <tbody>
            <tr><th scope="row">Seller</th><td>{{ $seller }}@if ($vatNumber) <br><span class="ix-hint">VAT number {{ $vatNumber }}</span>@endif</td></tr>
            <tr><th scope="row">Bought by</th><td>{{ $buyer->name }} ({{ $buyer->email }})</td></tr>
            <tr><th scope="row">Receipt number</th><td>R-{{ str_pad((string) $reg->id, 6, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><th scope="row">Date paid</th><td>{{ $reg->paid_at->timezone($tz)->format('j M Y, H:i') }}</td></tr>
            <tr><th scope="row">Seminar</th><td>{{ $seminar->starts_at->timezone($tz)->format('D j M Y, H:i') }}</td></tr>
            <tr><th scope="row">Ticket</th><td>{{ $reg->ticket_ref }}</td></tr>
            <tr><th scope="row">Amount paid</th><td>{{ \App\Models\Seminar::money((int) $reg->amount_cents, $reg->currency ?? 'ZAR') }}@if ($taxInvoice) <span class="ix-hint">(includes VAT of {{ \App\Models\Seminar::money($vatIncluded) }} at {{ $rate }}%)</span>@endif</td></tr>
            @if ($reg->refunded_cents > 0)<tr><th scope="row">Refunded</th><td>{{ \App\Models\Seminar::money((int) $reg->refunded_cents) }}</td></tr>@endif
        </tbody>
    </table>
    <p class="ix-hint" style="margin-top: 12px;">Payment was collected for the seller by InfoDot through Paystack.</p>
</x-app-layout>
