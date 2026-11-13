<x-app-layout title="Deliveries">
    @include('admin.platforms._tabs', ['current' => 'outbox'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Deliveries</h1>
            <p class="ix-lede">Each entitlement change is sent to the platform it is about. A platform that cannot be reached is retried with growing delays; an event it refuses is parked until someone retries it here. Every platform also reads the entitlement when a person signs in, so a late delivery never opens or closes a door by itself.</p>
        </div>
    </div>

    @include('admin.platforms._flash')

    <nav class="ix-filters" aria-label="Delivery state">
        @foreach (['waiting' => 'Waiting', 'parked' => 'Parked', 'delivered' => 'Delivered'] as $value => $text)
            <a class="ix-pill" href="{{ route('admin.platforms.outbox', ['state' => $value]) }}" @if ($state === $value) aria-current="true" @endif>{{ $text }} <span class="ix-pill__n">{{ $counts[$value] }}</span></a>
        @endforeach
    </nav>

    <section class="ix-panel ix-panel--clip ix-vgap" aria-label="{{ ucfirst($state) }} events">
        @if ($rows->isEmpty())
            <p class="ix-hint ix-panel__body">{{ ['waiting' => 'Nothing is waiting. Every change has been sent or parked.', 'parked' => 'Nothing is parked. No platform has refused an event.', 'delivered' => 'Nothing has been delivered yet.'][$state] }}</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Event</th><th>Platform</th><th>Company</th><th>Change</th><th class="num">Attempts</th><th>{{ $state === 'delivered' ? 'Delivered' : 'Next attempt' }}</th><th>Last answer</th>@unless ($state === 'delivered')<th><span class="ix-sr">Retry</span></th>@endunless</tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $payload = json_decode((string) $row->payload, true) ?: [];
                                $company = $companies[$row->team_uuid] ?? null;
                                $when = $state === 'delivered' ? $row->sent_at : $row->next_attempt_at;
                            @endphp
                            <tr>
                                <td><span class="ix-ref">{{ $row->event_id }}</span></td>
                                <td data-label="Platform">{{ $row->platform_key }}</td>
                                <td data-label="Company">@if ($company)<a class="title" href="{{ route('admin.platforms.company', $company) }}">{{ $company->name }}</a>@else<span class="muted">Company no longer exists</span>@endif</td>
                                <td data-label="Change">{{ str_replace('_', ' ', (string) ($payload['type'] ?? '')) }} <span class="muted">({{ str_replace('_', ' ', (string) ($payload['status'] ?? '')) }})</span></td>
                                <td class="num" data-label="Attempts">{{ $row->attempts }}</td>
                                <td class="muted" data-label="{{ $state === 'delivered' ? 'Delivered' : 'Next attempt' }}">{{ $state === 'parked' ? 'Not scheduled' : ($when ? \Illuminate\Support\Carbon::parse($when)->format('j M, H:i') : 'Now') }}</td>
                                <td class="ix-adm__soft" data-label="Last answer">{{ $row->last_error ?? ($state === 'delivered' ? 'Accepted' : 'Not tried yet') }}</td>
                                @unless ($state === 'delivered')
                                    <td>
                                        <form method="post" action="{{ route('admin.platforms.outbox.retry', $row->id) }}">
                                            @csrf
                                            <input type="hidden" name="state" value="{{ $state }}">
                                            <button type="submit" class="ix-btn ix-btn--sm">Retry now<span class="ix-sr"> {{ $row->event_id }}</span></button>
                                        </form>
                                    </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @include('partials.pager', ['pager' => $rows, 'label' => 'Deliveries'])
</x-app-layout>
