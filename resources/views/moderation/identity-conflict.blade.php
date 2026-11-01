<x-app-layout>
    @php
        $sides = [
            ['Account under review', $c->subject, $subjectLogo, $subjectCover],
            ['Verified business', $c->protected, $protectedLogo, $protectedCover],
        ];
        $open = in_array($c->status, ['open', 'escalated'], true);
    @endphp

    @include('livewire.moderation._tabs', ['current' => 'identity'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Identity conflict #{{ $c->id }}</h1>
            <p class="ix-lede"><span class="ix-idlevel ix-idlevel--{{ $c->level }}">{{ $c->level }}</span> · score {{ $c->score }} · {{ \App\Models\IdentityConflict::STATUSES[$c->status] ?? $c->status }} · found {{ $c->created_at->format('j M Y, H:i') }}, last seen {{ $c->last_seen_at->diffForHumans() }} ({{ str_replace('_', ' ', $c->trigger) }})</p>
        </div>
        <a href="{{ route('moderation.identity') }}" class="ix-btn ix-btn--ghost">All conflicts</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @unless ($canDecide)
        <p class="ix-alert" role="note">You belong to one of these businesses, so another reviewer must decide this.</p>
    @endunless

    <section class="ix-panel" aria-labelledby="sig-h">
        <div class="ix-panel__head"><h2 id="sig-h">Why it was flagged</h2></div>
        <div class="ix-panel__body">
            <ul class="ix-idsignals">
                @foreach ($c->signals as $signal)
                    <li><span>{{ $signal['detail'] }}</span><span class="w">+{{ $signal['weight'] }}</span></li>
                @endforeach
                <li><span class="total">Score</span><span class="w total">{{ $c->score }}</span></li>
            </ul>
            <p class="ix-hint ix-vgap">Suggested: {{ \App\Models\IdentityConflict::RESPONSES[$c->level] }} Registration numbers are compared as hashes — neither number is shown here.</p>
        </div>
    </section>

    <div class="ix-idpair">
        @foreach ($sides as [$title, $team, $logo, $cover])
            <section class="ix-vdesk__col" aria-label="{{ $title }}">
                <h2>{{ $title }}</h2>
                <div class="ix-idpair__media">
                    <span class="ix-biz__logo">@if ($logo)<img src="{{ route('attachments.show', $logo) }}" alt="{{ $team?->name }} logo" width="72" height="72">@else{{ mb_substr(trim((string) $team?->name), 0, 1) }}@endif</span>
                    <div>
                        <b>{{ $team?->name ?? 'Deleted' }}</b>
                        @if ($team?->code)<div><span class="ix-ref">{{ $team->code }}</span></div>@endif
                    </div>
                </div>
                @if ($cover)<img class="ix-idpair__cover" src="{{ route('attachments.show', $cover) }}" alt="{{ $team?->name }} cover photograph">@endif
                <dl class="ix-biz__facts ix-vgap">
                    <div><dt>Trading as</dt><dd>{{ $team?->profile?->trading_name ?: '—' }}</dd></div>
                    <div><dt>Registered in</dt><dd>{{ $team?->profile?->registration_country ?: '—' }}</dd></div>
                    <div><dt>Website</dt><dd>{{ $team?->profile?->website ?: '—' }}</dd></div>
                    <div><dt>Account created</dt><dd>{{ $team?->created_at?->format('j M Y') }}</dd></div>
                    <div><dt>Owner</dt><dd>{{ $team?->owner?->name ?? '—' }}</dd></div>
                </dl>
                @if ($team && ! $team->personal_team)<p class="ix-vgap"><a href="{{ route('business.show', $team) }}">Business page</a></p>@endif
            </section>
        @endforeach
    </div>

    @if ($open && $canDecide)
        <section class="ix-panel" aria-labelledby="dec-h">
            <div class="ix-panel__head"><h2 id="dec-h">Decide</h2></div>
            <div class="ix-panel__body ix-vactions">
                <details>
                    <summary class="ix-btn">Different businesses — dismiss</summary>
                    <form method="post" action="{{ route('moderation.identity.resolve', [$c, 'dismiss']) }}" class="ix-form ix-vgap">
                        @csrf
                        <div class="ix-field"><label class="ix-label" for="note-dismiss">Why (reviewers only)</label><textarea id="note-dismiss" name="note" class="ix-input" rows="2" minlength="10" maxlength="2000" required></textarea></div>
                        <div><button type="submit" class="ix-btn">Dismiss</button></div>
                    </form>
                </details>
                <details>
                    <summary class="ix-btn">They are related — record the link</summary>
                    <form method="post" action="{{ route('moderation.identity.resolve', [$c, 'relate']) }}" class="ix-form ix-vgap">
                        @csrf
                        <div class="ix-field">
                            <label class="ix-label" for="kind">“{{ $c->subject?->name }}” is a…</label>
                            <select id="kind" name="kind" class="ix-input">@foreach ($kinds as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="ix-field"><label class="ix-label" for="note-relate">How you know (reviewers only)</label><textarea id="note-relate" name="note" class="ix-input" rows="2" minlength="10" maxlength="2000" required></textarea></div>
                        <div><button type="submit" class="ix-btn ix-btn--primary">Record as related</button></div>
                    </form>
                </details>
                @if ($c->status === 'open')
                    <details>
                        <summary class="ix-btn ix-btn--danger">Escalate for investigation</summary>
                        <form method="post" action="{{ route('moderation.identity.resolve', [$c, 'escalate']) }}" class="ix-form ix-vgap">
                            @csrf
                            <div class="ix-field"><label class="ix-label" for="note-escalate">What needs investigating (reviewers only)</label><textarea id="note-escalate" name="note" class="ix-input" rows="2" minlength="10" maxlength="2000" required></textarea></div>
                            <div><button type="submit" class="ix-btn ix-btn--danger">Escalate</button></div>
                        </form>
                    </details>
                @endif
                <p class="ix-hint">Nothing here is shown to the account under review except that an extra check applies. Neither side is called an impersonator.</p>
            </div>
        </section>
    @elseif (! $open)
        <section class="ix-panel"><div class="ix-panel__body"><p>{{ \App\Models\IdentityConflict::STATUSES[$c->status] }} by {{ $c->resolver?->name ?? 'the verified business' }} on {{ $c->resolved_at?->format('j M Y') }}.</p>@if ($c->resolution_note)<p class="ix-hint">{{ $c->resolution_note }}</p>@endif</div></section>
    @endif
</x-app-layout>
