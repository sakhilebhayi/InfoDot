<x-app-layout>
    @php($isCredit = $invoice->kind === 'credit_note')
    @php($m = fn (int $c) => \App\Models\Seminar::money(abs($c), $invoice->currency))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $isCredit ? 'Credit note' : 'Tax invoice' }} {{ $invoice->number }}</h1>
            <p class="ix-lede">Commission for {{ $invoice->period }}, issued {{ $invoice->issued_at->format('j M Y') }}</p>
        </div>
        <button class="ix-btn" type="button" onclick="window.print()">Print</button>
    </div>
    <table class="ix-table" style="max-width: 720px;">
        <tbody>
            <tr><th scope="row">From</th><td>{{ $invoice->issuer['name'] }}<br><span class="ix-hint">{{ $invoice->issuer['address'] }}<br>VAT number {{ $invoice->issuer['vat_number'] }}</span></td></tr>
            <tr><th scope="row">To</th><td>{{ $host->name }}</td></tr>
        </tbody>
    </table>
    <table class="ix-table" style="margin-top: 16px;">
        <thead><tr><th scope="col">Date</th><th scope="col">What</th><th scope="col">Ticket amount</th><th scope="col">Commission</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines as $l)
                <tr>
                    <td>{{ $l['on'] }}</td>
                    <td>{{ ['sale' => 'Ticket sold', 'refund' => 'Refund', 'chargeback' => 'Chargeback'][$l['type']] ?? $l['type'] }}: {{ $l['seminar'] }} <span class="ix-hint">{{ $l['ticket'] }}</span></td>
                    <td>{{ $l['amount_cents'] < 0 ? '-' : '' }}{{ $m($l['amount_cents']) }}</td>
                    <td>{{ $l['fee_cents'] < 0 ? '-' : '' }}{{ $m($l['fee_cents']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="ix-table" style="max-width: 420px; margin-top: 16px;">
        <tbody>
            <tr><th scope="row">{{ $isCredit ? 'Credit' : 'Commission' }} excluding VAT</th><td>{{ $m($invoice->excl_vat_cents) }}</td></tr>
            <tr><th scope="row">VAT at {{ $invoice->issuer['vat_rate_percent'] }}%</th><td>{{ $m($invoice->vat_cents) }}</td></tr>
            <tr><th scope="row">Total {{ $isCredit ? 'credited' : 'kept by InfoDot' }}</th><td><strong>{{ $m($invoice->fee_cents) }}</strong></td></tr>
        </tbody>
    </table>
    <p class="ix-hint" style="margin-top: 12px;">{{ $invoice->issuer['commission_includes_vat'] ? 'The commission percentage includes VAT, so the VAT above is part of the amount kept, not added to it.' : 'VAT is added to the commission.' }} Settled by deduction from the ticket payments.</p>
</x-app-layout>
