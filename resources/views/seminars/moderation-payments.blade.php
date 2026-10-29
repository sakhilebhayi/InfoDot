<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Seminar payments</h1>
            <p class="ix-lede">Ticket payments that need a person. Disputes are answered in the Paystack dashboard; nothing here submits evidence.</p>
        </div>
    </div>
    @foreach ([['Disputes', $disputes, 'dispute'], ['Refunds asked for but not confirmed for over an hour', $stuck, 'stuck']] as [$heading, $rows, $kind])
        <section style="margin-bottom: 24px;">
            <h2 class="ix-label">{{ $heading }} ({{ $rows->count() }})</h2>
            @if ($rows->isEmpty())
                <p class="ix-hint">None.</p>
            @else
                <table class="ix-table">
                    <thead><tr><th scope="col">Seminar</th><th scope="col">Ticket</th><th scope="col">Amount</th><th scope="col">State</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td><a href="{{ route('seminars.show', $r->seminar_id) }}" style="text-decoration: underline;">{{ $r->seminar->title }}</a></td>
                                <td>{{ $r->ticket_ref }}</td>
                                <td>{{ \App\Models\Seminar::money((int) $r->amount_cents) }}</td>
                                <td>{{ $kind === 'dispute' ? 'Dispute '.$r->dispute_status : 'Refund requested '.$r->refund_requested_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
    <section>
        <h2 class="ix-label">Callbacks from Billing that did not match ({{ $odd->count() }})</h2>
        @if ($odd->isEmpty())
            <p class="ix-hint">None in the last 30 days.</p>
        @else
            <ul class="ix-list">
                @foreach ($odd as $e)
                    <li style="padding: 10px 14px;">{{ $e->event }} · {{ $e->ticket_ref }} · {{ $e->outcome }} <span class="ix-ref">{{ $e->created_at->diffForHumans() }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
</x-app-layout>
