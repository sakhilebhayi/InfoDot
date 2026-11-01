<x-app-layout>
    @include('livewire.moderation._tabs', ['current' => 'identity'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Identity conflicts</h1>
            <p class="ix-lede">Business accounts whose details match a verified business. A match is a reason to look, not a finding: it is often a branch, a franchise or a business with a similar name. Medium and above withhold verification from the account until you decide.</p>
        </div>
    </div>

    <section class="ix-panel ix-panel--clip" aria-label="Open">
        <div class="ix-panel__head"><h2>Open</h2><span class="ix-hint">{{ $open->count() }}</span></div>
        @if ($open->isEmpty())
            <p class="ix-hint ix-panel__body">No open conflicts.</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>Level</th><th>Account</th><th>Matches verified</th><th class="num">Score</th><th>Found</th></tr></thead>
                    <tbody>
                        @foreach ($open as $c)
                            <tr>
                                <td><span class="ix-idlevel ix-idlevel--{{ $c->level }}">{{ $c->level }}</span>@if ($c->status === 'escalated') <span class="ix-chip ix-chip--open">escalated</span>@endif</td>
                                <td><a class="title" href="{{ route('moderation.identity.show', $c) }}">{{ $c->subject?->name ?? 'Deleted account' }}</a>@if ($c->subject?->code) <span class="ix-ref">{{ $c->subject->code }}</span>@endif</td>
                                <td>{{ $c->protected?->name }}@if ($c->protected?->code) <span class="ix-ref">{{ $c->protected->code }}</span>@endif</td>
                                <td class="num">{{ $c->score }}</td>
                                <td class="muted"><time datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->created_at->diffForHumans() }}</time></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ix-panel ix-panel--clip" aria-label="Recently resolved">
        <div class="ix-panel__head"><h2>Recently resolved</h2></div>
        @if ($recent->isEmpty())
            <p class="ix-hint ix-panel__body">Nothing resolved yet.</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>Account</th><th>Verified business</th><th>Outcome</th><th>When</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $c)
                            <tr>
                                <td><a class="title" href="{{ route('moderation.identity.show', $c) }}">{{ $c->subject?->name }}</a></td>
                                <td>{{ $c->protected?->name }}</td>
                                <td class="muted">{{ \App\Models\IdentityConflict::STATUSES[$c->status] ?? $c->status }}</td>
                                <td class="muted">{{ $c->resolved_at?->format('j M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
