<x-app-layout>
    @include('livewire.moderation._tabs', ['current' => 'trust'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Investigation #{{ $inv->id }}: {{ $team->name }}</h1>
            <p class="ix-lede">{{ \App\Models\BusinessInvestigation::CONCERNS[$inv->concern] ?? $inv->concern }}. <b>{{ $inv->statusLabel() }}</b>. Opened by {{ $inv->opener?->name ?? 'a former reviewer' }} on {{ $inv->created_at->format('j M Y') }}@if ($inv->decided_at); decided by {{ $inv->decider?->name }} on {{ $inv->decided_at->format('j M Y') }}@endif. {{ $inv->restricted ? 'Out of the directory.' : 'Listed as usual.' }}</p>
        </div>
        <a href="{{ route('moderation.trust') }}" class="ix-btn ix-btn--ghost">All business trust</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @unless ($canAct)<p class="ix-alert" role="note">You belong to {{ $team->name }}, so another reviewer must act on this.</p>@endunless

    <div class="ix-convo">
        <div class="ix-convo__main">
            <section class="ix-panel">
                <div class="ix-panel__head"><h2>Trail</h2></div>
                <div class="ix-panel__body">
                    <ol class="ix-ledger">
                        @foreach ($inv->events as $e)
                            <li><time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->format('j M Y, H:i') }}</time><span><b>{{ $e->user?->name ?? 'A former reviewer' }}</b> {{ mb_strtolower($e->label()) }}@if ($e->note)<span class="ix-standing__msg">{{ $e->note }}</span>@endif</span></li>
                        @endforeach
                    </ol>
                </div>
            </section>

            @if ($canAct && $inv->isOpen())
                <section class="ix-panel" aria-labelledby="act-h">
                    <div class="ix-panel__head"><h2 id="act-h">Act</h2></div>
                    <div class="ix-panel__body ix-vactions">
                        <details><summary class="ix-btn">Add a note</summary>
                            <form method="post" action="{{ route('moderation.trust.act', [$inv, 'note']) }}" class="ix-form ix-vgap">@csrf<div class="ix-field"><label class="ix-label" for="t-note">Note (reviewers only)</label><textarea id="t-note" name="text" class="ix-textarea" rows="2" minlength="10" maxlength="3000" required></textarea></div><div><button class="ix-btn">Add</button></div></form>
                        </details>
                        <details><summary class="ix-btn">Write to the business</summary>
                            <form method="post" action="{{ route('moderation.trust.act', [$inv, 'message']) }}" class="ix-form ix-vgap">@csrf<div class="ix-field"><label class="ix-label" for="t-msg">Message (the business’s owner and administrators read it on their standing page)</label><textarea id="t-msg" name="text" class="ix-textarea" rows="3" minlength="10" maxlength="3000" required>{{ $inv->message_to_business }}</textarea></div><div><button class="ix-btn">Send</button></div></form>
                        </details>
                        @if ($inv->restricted)
                            <form method="post" action="{{ route('moderation.trust.act', [$inv, 'lift']) }}">@csrf<button class="ix-btn">Return it to the directory</button></form>
                        @else
                            <form method="post" action="{{ route('moderation.trust.act', [$inv, 'restrict']) }}">@csrf<button class="ix-btn ix-btn--danger" @if(! $inv->message_to_business) disabled title="Write to the business first" @endif>Take it out of the directory</button></form>
                        @endif
                        <details><summary class="ix-btn ix-btn--primary">Conclude</summary>
                            <form method="post" action="{{ route('moderation.trust.act', [$inv, 'conclude']) }}" class="ix-form ix-vgap">@csrf
                                <fieldset class="ix-choice"><legend class="ix-label">Outcome</legend>
                                    @foreach ([\App\Models\BusinessInvestigation::CLEARED, \App\Models\BusinessInvestigation::CORRECTED, \App\Models\BusinessInvestigation::CONFIRMED_IMPERSONATION, \App\Models\BusinessInvestigation::CONFIRMED_MISCONDUCT] as $o)
                                        <label class="ix-choice__opt"><input type="radio" name="outcome" value="{{ $o }}" required><span><b>{{ \App\Models\BusinessInvestigation::STATUSES[$o] }}</b>@if (in_array($o, \App\Models\BusinessInvestigation::CONFIRMED, true))<span class="ix-hint">Keeps it out of the directory and withdraws any verification. The business can appeal to another reviewer.</span>@else<span class="ix-hint">Returns it to the directory.</span>@endif</span></label>
                                    @endforeach
                                </fieldset>
                                <div class="ix-field"><label class="ix-label" for="t-out">What to tell the business</label><textarea id="t-out" name="text" class="ix-textarea" rows="3" minlength="20" maxlength="3000" required></textarea></div>
                                <div><button class="ix-btn ix-btn--primary">Record the outcome</button></div>
                            </form>
                        </details>
                    </div>
                </section>
            @endif
        </div>

        <aside class="ix-convo__rail">
            <section class="ix-biz__card">
                <h2>The business</h2>
                <p><a href="{{ route('business.show', $team) }}">{{ $team->name }}</a>@if ($team->code) <span class="ix-ref">{{ $team->code }}</span>@endif</p>
                <p class="ix-trust__line">Verification: {{ $standing?->label() ?? 'never applied' }}.</p>
                @if ($inv->identity_conflict_id)<p class="ix-trust__line"><a href="{{ route('moderation.identity.show', $inv->identity_conflict_id) }}">Identity conflict #{{ $inv->identity_conflict_id }}</a></p>@endif
                @if ($appeal)<p class="ix-trust__line"><a href="{{ route('moderation.trust.appeal', $appeal) }}">Appeal: {{ mb_strtolower(\App\Models\BusinessAppeal::STATUSES[$appeal->status]) }}</a></p>@endif
            </section>
            <section class="ix-biz__card">
                <h2>Reports</h2>
                @if ($inv->reports->isEmpty())<p class="ix-hint">None linked.</p>@else
                    <ul class="ix-trust__checks">@foreach ($inv->reports as $r)<li><a href="{{ route('moderation.trust.report', $r) }}">#{{ $r->id }} {{ $r->categoryLabel() }}</a></li>@endforeach</ul>
                @endif
                @if ($unlinked->isNotEmpty())<p class="ix-trust__line">{{ $unlinked->count() }} more open {{ \Illuminate\Support\Str::plural('report', $unlinked->count()) }} about this business can be linked from the report page.</p>@endif
            </section>
        </aside>
    </div>
</x-app-layout>
