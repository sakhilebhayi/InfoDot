<x-app-layout title="Confirmed payments">
    @include('admin.platforms._tabs', ['current' => 'payments'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Confirmed payments</h1>
            <p class="ix-lede">Only what Dot.Finance has confirmed with a signed message is listed and counted here. Nothing on this page is an estimate, and nothing in InfoDot can add to it.</p>
        </div>
    </div>

    @if ($confirmations->isEmpty())
        <section class="ix-panel" aria-label="Confirmed payments">
            <p class="ix-hint ix-panel__body">Dot.Finance has not confirmed any payment yet. Until it sends its first signed confirmation, no platform can be paid for and this page stays empty.</p>
        </section>
    @else
        <section class="ix-panel" aria-labelledby="total-h">
            <div class="ix-panel__head"><h2 id="total-h">Received, by currency</h2></div>
            <div class="ix-panel__body">
                <dl class="ix-biz__facts">
                    @forelse ($received as $line)
                        <div><dt>{{ $line->currency }}</dt><dd><strong>{{ \App\Support\Money::format((int) $line->total, $line->currency) }}</strong> in {{ $line->n }} {{ (int) $line->n === 1 ? 'payment' : 'payments' }}@if (($refunded[$line->currency] ?? 0) > 0), of which {{ \App\Support\Money::format((int) $refunded[$line->currency], $line->currency) }} was refunded @endif</dd></div>
                    @empty
                        <div><dt>Received</dt><dd>Nothing yet. The events below are failures, refunds or cancellations.</dd></div>
                    @endforelse
                </dl>
            </div>
        </section>

        <section class="ix-panel ix-panel--clip ix-vgap" aria-label="Confirmed events">
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>When</th><th>Event</th><th>Company</th><th>Platform</th><th>Plan</th><th class="num">Amount</th><th>Paid to</th><th>Reference</th><th>Result</th></tr></thead>
                    <tbody>
                        @foreach ($confirmations as $c)
                            <tr>
                                <td class="muted"><time datetime="{{ $c->occurred_at?->toIso8601String() }}">{{ $c->occurred_at?->format('j M Y, H:i') }}</time></td>
                                <td class="title">{{ ucfirst(str_replace('_', ' ', $c->event)) }}</td>
                                <td data-label="Company">@if ($c->team)<a class="title" href="{{ route('admin.platforms.company', ['team' => $c->team, 'platform' => $c->platform_key]) }}">{{ $c->team->name }}</a>@endif</td>
                                <td data-label="Platform">{{ $c->platform_key }}</td>
                                <td class="muted" data-label="Plan">{{ $c->plan?->name ?? 'None named' }}</td>
                                <td class="num" data-label="Amount">{{ $c->amount_minor === null ? '' : \App\Support\Money::format($c->amount_minor, $c->currency) }}</td>
                                <td class="muted" data-label="Paid to">{{ $c->period_ends_at?->format('j M Y') ?? '' }}</td>
                                <td data-label="Reference"><span class="ix-ref">{{ $c->reference }}</span></td>
                                <td>@if ($c->outcome === 'applied')<span class="ix-chip ix-chip--done">Applied</span>@else<span class="ix-chip">Ignored: older than one already applied, or nothing to change</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @include('partials.pager', ['pager' => $confirmations, 'label' => 'Confirmed payments'])
    @endif
</x-app-layout>
