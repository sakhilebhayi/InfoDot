<x-app-layout>
    @php($tz = config('app.timezone'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $taxInvoice ? 'Tax invoice' : 'Receipt' }}</h1>
            <p class="ix-lede">{{ $seminar->title }}</p>
        </div>
        <button class="ix-btn" type="button" x-data @click="window.print()">Print</button>
    </div>
    <table class="ix-table" style="max-width: 640px;">
        <tbody>
            <tr><th scope="row">Seller</th><td>{{ $seller }}@if ($vatNumber) <br><span class="ix-hint">VAT number {{ $vatNumber }}</span>@endif</td></tr>
            <tr><th scope="row">Bought by</th><td>{{ $buyer->name }} ({{ $buyer->email }})</td></tr>
            <tr><th scope="row">Receipt number</th><td>{{ $sale->receiptNumber() }}</td></tr>
            <tr><th scope="row">Date paid</th><td>{{ $sale->paid_at->timezone($tz)->format('j M Y, H:i') }}</td></tr>
            <tr><th scope="row">Seminar</th><td>{{ $seminar->starts_at->timezone($tz)->format('D j M Y, H:i') }}</td></tr>
            <tr><th scope="row">Ticket</th><td>{{ $sale->ticket_ref }}</td></tr>
            <tr><th scope="row">Amount paid</th><td>{{ \App\Models\Seminar::money((int) $sale->amount_cents, $sale->currency ?? 'ZAR') }}@if ($taxInvoice) <span class="ix-hint">(includes VAT of {{ \App\Models\Seminar::money($vatIncluded) }} at {{ $rate }}%)</span>@endif</td></tr>
            @if ($sale->refunded_cents > 0)<tr><th scope="row">Refunded</th><td>{{ \App\Models\Seminar::money((int) $sale->refunded_cents) }}</td></tr>@endif
        </tbody>
    </table>
    @if ($others->isNotEmpty())
        <p class="ix-hint" style="margin-top: 12px;">You bought a ticket to this seminar more than once. Earlier receipts:
            @foreach ($others as $other)
                <a href="{{ route('seminars.receipt', ['seminar' => $seminar, 'sale' => $other->id]) }}" style="text-decoration: underline;">{{ $other->receiptNumber() }}</a>@if (! $loop->last), @endif
            @endforeach
        </p>
    @endif
    <p class="ix-hint" style="margin-top: 12px;">Payment was collected for the seller by InfoDot through Paystack.</p>
</x-app-layout>
