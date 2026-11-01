<x-app-layout>
    @include('livewire.moderation._tabs', ['current' => 'verifications'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Business verification</h1>
            <p class="ix-lede">Applications from businesses that want InfoDot’s approval plate. Approval needs at least {{ config('verification.minimum_checks') }} confirmed checks, one of them a document review — and you cannot decide for a business you belong to.</p>
        </div>
    </div>

    @foreach ([
        ['Waiting for a reviewer', $waiting, 'Nothing is waiting. New applications and businesses that answered a request arrive here.'],
        ['With the business', $withBusiness, 'No business owes you information right now.'],
        ['Suspended', $standing, 'No verification is suspended.'],
        ['Recently decided', $recent, 'No decisions yet.'],
    ] as [$title, $rows, $empty])
        <section class="ix-panel ix-panel--clip" aria-label="{{ $title }}">
            <div class="ix-panel__head"><h2>{{ $title }}</h2><span class="ix-hint">{{ $rows->count() }}</span></div>
            @if ($rows->isEmpty())
                <p class="ix-hint ix-panel__body">{{ $empty }}</p>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table">
                        <thead><tr><th>Business</th><th>Status</th><th>Kind</th><th class="num">Documents</th><th>Since</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $v)
                                <tr>
                                    <td><a class="title" href="{{ route('moderation.verifications.show', $v) }}">{{ $v->team?->name ?? 'Deleted business' }}</a>@if ($v->team?->code) <span class="ix-ref">{{ $v->team->code }}</span>@endif</td>
                                    <td><span class="ix-chip {{ $v->status === 'verified' ? 'ix-chip--done' : (in_array($v->status, ['pending', 'under_review', 'info_required'], true) ? 'ix-chip--open' : '') }}">{{ $v->verified_at && $v->status === 'under_review' ? 'Details changed' : $v->label() }}</span></td>
                                    <td class="muted">{{ \App\Models\BusinessVerification::CATEGORIES[$v->category] ?? $v->category }}</td>
                                    <td class="num">{{ $v->evidence_count }}</td>
                                    <td class="muted"><time datetime="{{ $v->updated_at->toIso8601String() }}">{{ $v->updated_at->diffForHumans() }}</time></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</x-app-layout>
