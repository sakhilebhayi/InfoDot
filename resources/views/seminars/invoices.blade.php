<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Commission invoices</h1>
            <p class="ix-lede">Each month InfoDot invoices you for the commission it kept on your tickets. It is taken from the ticket payments before you are paid, so there is nothing to pay. A month where refunds outweigh sales is a credit note.</p>
        </div>
    </div>
    @if ($invoices->isEmpty())
        <section class="ix-empty"><h2>No invoices yet</h2><p>The first one is issued after the month in which you sell a ticket.</p></section>
    @else
        <table class="ix-table">
            <thead><tr><th scope="col">Number</th><th scope="col">Month</th><th scope="col">Type</th><th scope="col">Commission</th><th scope="col">VAT included</th></tr></thead>
            <tbody>
                @foreach ($invoices as $i)
                    <tr>
                        <td><a href="{{ route('seminars.invoice', $i) }}" style="text-decoration: underline;">{{ $i->number }}</a></td>
                        <td>{{ $i->period }}</td>
                        <td>{{ $i->kind === 'credit_note' ? 'Credit note' : 'Tax invoice' }}</td>
                        <td>{{ \App\Models\Seminar::money(abs($i->fee_cents), $i->currency) }}{{ $i->fee_cents < 0 ? ' credit' : '' }}</td>
                        <td>{{ \App\Models\Seminar::money(abs($i->vat_cents), $i->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-app-layout>
