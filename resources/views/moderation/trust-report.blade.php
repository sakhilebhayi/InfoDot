<x-app-layout>
    @include('livewire.moderation._tabs', ['current' => 'trust'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Report #{{ $report->id }}: {{ $team->name }}</h1>
            <p class="ix-lede"><span class="ix-idlevel ix-idlevel--{{ $report->priority === 'urgent' ? 'critical' : ($report->priority === 'high' ? 'medium' : 'low') }}">{{ $report->priority }}</span> {{ $report->categoryLabel() }}. From {{ $report->reporter?->name ?? 'a deleted account' }}, {{ $report->created_at->format('j M Y, H:i') }}. {{ \App\Models\BusinessReport::STATUSES[$report->status] }}.</p>
        </div>
        <a href="{{ route('moderation.trust') }}" class="ix-btn ix-btn--ghost">All business trust</a>
    </div>

    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @unless ($canAct)<p class="ix-alert" role="note">You belong to {{ $team->name }}, so another reviewer must act on this.</p>@endunless

    <div class="ix-convo">
        <div class="ix-convo__main">
            <section class="ix-panel">
                <div class="ix-panel__head"><h2>What they reported</h2></div>
                <div class="ix-panel__body">
                    <p class="ix-msg__body">{{ $report->detail }}</p>
                    @if ($files->isNotEmpty())
                        <ul class="ix-msg__files">@foreach ($files as $f)<li><a href="{{ route('attachments.show', $f) }}" target="_blank" rel="noopener">{{ $f->name }}</a> <span class="ix-hint">{{ $f->sizeLabel() }}</span></li>@endforeach</ul>
                    @endif
                </div>
            </section>

            @if ($canAct && $report->status === 'open')
                <section class="ix-panel" aria-labelledby="dec-h">
                    <div class="ix-panel__head"><h2 id="dec-h">Decide</h2></div>
                    <div class="ix-panel__body ix-vactions">
                        @if ($open)
                            <details><summary class="ix-btn ix-btn--primary">Add to the open investigation</summary>
                                <form method="post" action="{{ route('moderation.trust.report.resolve', [$report, 'link']) }}" class="ix-form ix-vgap">@csrf
                                    <div class="ix-field"><label class="ix-label" for="n-link">Note for the investigation</label><textarea id="n-link" name="note" class="ix-textarea" rows="2" minlength="10" maxlength="2000" required></textarea></div>
                                    <div><button class="ix-btn ix-btn--primary">Link it</button></div>
                                </form>
                            </details>
                        @else
                            <details><summary class="ix-btn ix-btn--primary">Open an investigation</summary>
                                @include('moderation._trust-open', ['team' => $team, 'reportIds' => [$report->id], 'concern' => in_array($report->category, ['impersonation', 'stolen_branding'], true) ? 'potential_impersonation' : 'reported_concern'])
                            </details>
                        @endif
                        <details><summary class="ix-btn">Handled — action taken</summary>
                            <form method="post" action="{{ route('moderation.trust.report.resolve', [$report, 'action']) }}" class="ix-form ix-vgap">@csrf
                                <div class="ix-field"><label class="ix-label" for="n-act">What was done (reviewers only)</label><textarea id="n-act" name="note" class="ix-textarea" rows="2" minlength="10" maxlength="2000" required></textarea></div>
                                <div><button class="ix-btn">Close as actioned</button></div>
                            </form>
                        </details>
                        <details><summary class="ix-btn">No problem found</summary>
                            <form method="post" action="{{ route('moderation.trust.report.resolve', [$report, 'dismiss']) }}" class="ix-form ix-vgap">@csrf
                                <div class="ix-field"><label class="ix-label" for="n-dis">Why (reviewers only)</label><textarea id="n-dis" name="note" class="ix-textarea" rows="2" minlength="10" maxlength="2000" required></textarea></div>
                                <div><button class="ix-btn">Dismiss</button></div>
                            </form>
                        </details>
                        <p class="ix-hint">The reporter is told only whether action was taken. The business is never told who reported it.</p>
                    </div>
                </section>
            @elseif ($report->resolution_note)
                <section class="ix-panel"><div class="ix-panel__body"><p>{{ \App\Models\BusinessReport::STATUSES[$report->status] }} by {{ $report->resolver?->name }} on {{ $report->resolved_at?->format('j M Y') }}.</p><p class="ix-hint">{{ $report->resolution_note }}</p></div></section>
            @endif
        </div>

        <aside class="ix-convo__rail">
            <section class="ix-biz__card">
                <h2>The business</h2>
                <p><a href="{{ route('business.show', $team) }}">{{ $team->name }}</a>@if ($team->code) <span class="ix-ref">{{ $team->code }}</span>@endif</p>
                <p class="ix-trust__line">Verification: {{ $team->standing?->label() ?? 'never applied' }}. Directory: {{ $team->isRestricted() ? 'taken out' : ($team->profile?->visibility ?? 'private') }}.</p>
                @if ($open)<p class="ix-trust__line"><a href="{{ route('moderation.trust.investigation', $open) }}">Open investigation #{{ $open->id }}</a></p>@endif
            </section>
            <section class="ix-biz__card">
                <h2>Other reports about it</h2>
                @if ($others->isEmpty())
                    <p class="ix-hint">None.</p>
                @else
                    <ul class="ix-trust__checks">@foreach ($others as $o)<li><a href="{{ route('moderation.trust.report', $o) }}">{{ $o->categoryLabel() }}</a> <span class="ix-hint">{{ $o->created_at->format('j M') }}, {{ mb_strtolower(\App\Models\BusinessReport::STATUSES[$o->status]) }}</span></li>@endforeach</ul>
                @endif
            </section>
        </aside>
    </div>
</x-app-layout>
