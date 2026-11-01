<x-app-layout>
    @include('livewire.moderation._tabs', ['current' => 'trust'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Business trust</h1>
            <p class="ix-lede">Reports about businesses, investigations and appeals. A report is a reason to look, not a finding. Nothing here is ever shown to the public; a business taken out of the directory is simply absent.</p>
        </div>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif

    <section class="ix-panel ix-panel--clip" aria-labelledby="rep-h">
        <div class="ix-panel__head"><h2 id="rep-h">Open reports</h2><span class="ix-hint">{{ $reports->count() }}</span></div>
        @if ($reports->isEmpty())
            <p class="ix-hint ix-panel__body">No open reports.</p>
        @else
            <div class="ix-tablewrap"><table class="ix-table">
                <thead><tr><th>Priority</th><th>Business</th><th>Concern</th><th>Received</th></tr></thead>
                <tbody>
                    @foreach ($reports as $r)
                        <tr>
                            <td><span class="ix-idlevel ix-idlevel--{{ $r->priority === 'urgent' ? 'critical' : ($r->priority === 'high' ? 'medium' : 'low') }}">{{ $r->priority }}</span></td>
                            <td><a class="title" href="{{ route('moderation.trust.report', $r) }}">{{ $r->team?->name }}</a>@if ($r->team?->code) <span class="ix-ref">{{ $r->team->code }}</span>@endif</td>
                            <td>{{ $r->categoryLabel() }}</td>
                            <td class="muted"><time datetime="{{ $r->created_at->toIso8601String() }}">{{ $r->created_at->diffForHumans() }}</time></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    <section class="ix-panel ix-panel--clip" aria-labelledby="inv-h">
        <div class="ix-panel__head"><h2 id="inv-h">Open investigations</h2><span class="ix-hint">{{ $investigations->count() }}</span></div>
        @if ($investigations->isEmpty())
            <p class="ix-hint ix-panel__body">No open investigations.</p>
        @else
            <div class="ix-tablewrap"><table class="ix-table">
                <thead><tr><th>Business</th><th>Concern</th><th class="num">Reports</th><th>Directory</th><th>Opened</th></tr></thead>
                <tbody>
                    @foreach ($investigations as $i)
                        <tr>
                            <td><a class="title" href="{{ route('moderation.trust.investigation', $i) }}">{{ $i->team?->name }}</a></td>
                            <td>{{ \App\Models\BusinessInvestigation::CONCERNS[$i->concern] ?? $i->concern }}</td>
                            <td class="num">{{ $i->reports_count }}</td>
                            <td>{{ $i->restricted ? 'Taken out' : 'Listed as usual' }}</td>
                            <td class="muted">{{ $i->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    <section class="ix-panel ix-panel--clip" aria-labelledby="ap-h">
        <div class="ix-panel__head"><h2 id="ap-h">Appeals</h2><span class="ix-hint">{{ $appeals->count() }}</span></div>
        @if ($appeals->isEmpty())
            <p class="ix-hint ix-panel__body">No open appeals.</p>
        @else
            <div class="ix-tablewrap"><table class="ix-table">
                <thead><tr><th>Business</th><th>Against</th><th>Received</th></tr></thead>
                <tbody>
                    @foreach ($appeals as $a)
                        <tr>
                            <td><a class="title" href="{{ route('moderation.trust.appeal', $a) }}">{{ $a->team?->name }}</a></td>
                            <td>{{ $a->subject_type === 'investigation' ? 'An investigation' : 'A verification decision' }}</td>
                            <td class="muted">{{ $a->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    @if ($recent->isNotEmpty())
        <section class="ix-panel ix-panel--clip" aria-labelledby="done-h">
            <div class="ix-panel__head"><h2 id="done-h">Recently concluded</h2></div>
            <div class="ix-tablewrap"><table class="ix-table">
                <thead><tr><th>Business</th><th>Outcome</th><th>When</th></tr></thead>
                <tbody>
                    @foreach ($recent as $i)
                        <tr><td><a class="title" href="{{ route('moderation.trust.investigation', $i) }}">{{ $i->team?->name }}</a></td><td>{{ $i->statusLabel() }}</td><td class="muted">{{ $i->decided_at?->format('j M Y') }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>
    @endif
</x-app-layout>
