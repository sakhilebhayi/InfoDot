<x-app-layout>
    @php
        $team = $v->team;
        $profile = $team?->profile;
        $differs = fn ($a, $b) => $a !== null && $b !== null && mb_strtolower(trim((string) $a)) !== mb_strtolower(trim((string) $b));
        $isStanding = $v->verified_at !== null;
        $eventLabels = [
            'submitted' => 'Application sent', 'evidence_added' => 'Document added', 'info_provided' => 'Business answered the request',
            'withdrawn' => 'Withdrawn by the business', 'email_code_sent' => 'Code sent to the official email', 'email_confirmed' => 'Official email confirmed',
            'review_started' => 'Review started', 'info_requested' => 'More information requested', 'approved' => 'Approved',
            'rejected' => 'Not approved', 'reconfirmed' => 'New details confirmed', 'suspended' => 'Suspended', 'reinstated' => 'Reinstated',
            'revoked' => 'Revoked', 'identity_changed' => 'Verified details changed', 'expired' => 'Lapsed', 'superseded' => 'Replaced by a renewal',
            'evidence_purged' => 'Documents deleted (retention)',
        ];
    @endphp

    @include('livewire.moderation._tabs', ['current' => 'verifications'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $team?->name ?? 'Deleted business' }}</h1>
            <p class="ix-lede">
                <span class="ix-chip {{ $v->status === 'verified' ? 'ix-chip--done' : 'ix-chip--open' }}">{{ $isStanding && $v->status === 'under_review' ? 'Verified details changed' : $v->label() }}</span>
                @if ($team?->code)<span class="ix-ref">{{ $team->code }}</span>@endif
                @if ($team && ! $team->personal_team)<a href="{{ route('business.show', $team) }}">Business page</a>@endif
            </p>
        </div>
        <a href="{{ route('moderation.verifications') }}" class="ix-btn ix-btn--ghost">All applications</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @if ($conflict)
        <p class="ix-alert" role="note">Identity conflict <a href="{{ route('moderation.identity.show', $conflict) }}">#{{ $conflict->id }}</a> ({{ $conflict->level }}) is open: this business matches a verified one. It cannot be approved until that is resolved.</p>
    @endif
    @unless ($canDecide)
        <p class="ix-alert" role="note">You belong to this business, so you can read the application but another reviewer must decide it.</p>
    @endunless

    <div class="ix-vdesk">
        <section class="ix-vdesk__col" aria-labelledby="app-h">
            <h2 id="app-h">What the business sent</h2>
            <dl class="ix-biz__facts">
                <div><dt>Registered name</dt><dd>{{ $v->registered_name }}</dd></div>
                <div><dt>Trading as</dt><dd>{{ $v->trading_name ?: '—' }}</dd></div>
                <div><dt>Kind</dt><dd>{{ \App\Models\BusinessVerification::CATEGORIES[$v->category] ?? $v->category }}</dd></div>
                <div><dt>Registration no.</dt><dd>{{ $v->registration_number ?: '—' }}</dd></div>
                <div><dt>Registered in</dt><dd>{{ $v->registration_country }}</dd></div>
                <div><dt>Website</dt><dd>@if ($v->website)<a href="{{ $v->website }}" rel="noopener nofollow noreferrer" target="_blank">{{ $v->website }}</a>@else — @endif</dd></div>
                <div><dt>Official email</dt><dd>{{ $v->official_email ?: '—' }} @if ($v->official_email_confirmed_at)<span class="ix-chip ix-chip--done">confirmed by code</span>@elseif ($v->official_email)<span class="ix-chip">not confirmed</span>@endif</dd></div>
                <div><dt>Telephone</dt><dd>{{ $v->phone ?: '—' }} <span class="ix-hint">(not verified — no SMS service)</span></dd></div>
                <div><dt>Address</dt><dd>{{ $v->address ?: '—' }}</dd></div>
                <div><dt>Applicant</dt><dd>{{ $v->applicant?->name ?? 'Account deleted' }} — {{ $v->applicant_role }}</dd></div>
                <div><dt>Declared</dt><dd>{{ $v->declared_at->format('j M Y, H:i') }}</dd></div>
            </dl>
        </section>

        <section class="ix-vdesk__col" aria-labelledby="pub-h">
            <h2 id="pub-h">What the business shows on InfoDot</h2>
            <dl class="ix-biz__facts">
                <div><dt>Account name</dt><dd @class(['ix-vdesk__diff' => $differs($team?->name, $v->registered_name) && $differs($team?->name, $v->trading_name)])>{{ $team?->name }}</dd></div>
                <div><dt>Trading as</dt><dd @class(['ix-vdesk__diff' => $differs($profile?->trading_name, $v->trading_name)])>{{ $profile?->trading_name ?: '—' }}</dd></div>
                <div><dt>Registration no.</dt><dd @class(['ix-vdesk__diff' => $differs($profile?->registration_number, $v->registration_number)])>{{ $profile?->registration_number ?: '—' }}</dd></div>
                <div><dt>Registered in</dt><dd>{{ $profile?->registration_country ?: '—' }}</dd></div>
                <div><dt>Website</dt><dd @class(['ix-vdesk__diff' => $differs($profile?->website, $v->website)])>{{ $profile?->website ?: '—' }}</dd></div>
                <div><dt>Account created</dt><dd>{{ $team?->created_at?->format('j M Y') }}</dd></div>
            </dl>
            <p class="ix-hint ix-vgap">Highlighted lines differ from the application.</p>
        </section>
    </div>

    <section class="ix-panel" aria-labelledby="ev-h">
        <div class="ix-panel__head"><h2 id="ev-h">Documents</h2></div>
        <div class="ix-panel__body">
            @if ($v->evidence_purged_at)
                <p class="ix-hint">Deleted on {{ $v->evidence_purged_at->format('j M Y') }} after the retention period.</p>
            @elseif ($v->evidence->isEmpty())
                <p class="ix-hint">No documents. Ask for them before deciding.</p>
            @else
                <ul class="ix-evidence">
                    @foreach ($v->evidence as $doc)
                        <li>
                            <span class="material-symbols-rounded" aria-hidden="true">{{ $doc->kind === 'pdf' ? 'description' : 'image' }}</span>
                            <span class="ix-evidence__name">{{ $doc->caption }}<small>{{ $doc->name }} · {{ number_format($doc->size / 1024) }} KB · added {{ $doc->created_at->format('j M Y') }}</small></span>
                            <a href="{{ route('attachments.show', $doc) }}" class="ix-btn ix-btn--ghost ix-btn--sm" target="_blank" rel="noopener">Open</a>
                        </li>
                    @endforeach
                </ul>
                <p class="ix-hint ix-vgap">Look for: the business name and number matching the application, a current date, and — for authority — the applicant named or signing for the business. Files are not malware-scanned on this host; open them in the browser viewer, not downloaded.</p>
            @endif
        </div>
    </section>

    @if ($canDecide)
        <section class="ix-panel" aria-labelledby="act-h">
            <div class="ix-panel__head"><h2 id="act-h">Decide</h2></div>
            <div class="ix-panel__body ix-vactions">
                @if ($v->status === \App\Models\BusinessVerification::PENDING)
                    <form method="post" action="{{ route('moderation.verifications.decide', [$v, 'review']) }}">@csrf<button type="submit" class="ix-btn">I’m reviewing this</button></form>
                @endif

                @if (in_array($v->status, [\App\Models\BusinessVerification::PENDING, \App\Models\BusinessVerification::UNDER_REVIEW], true) && ! $isStanding)
                    <form method="post" action="{{ route('moderation.verifications.decide', [$v, 'approve']) }}" class="ix-form">
                        @csrf
                        <fieldset class="ix-vchecks">
                            <legend class="ix-label">What you confirmed (at least {{ config('verification.minimum_checks') }}, documents always)</legend>
                            @foreach ($checks as $key => $label)
                                @php $blocked = $key === 'official_email' && ! $v->official_email_confirmed_at; @endphp
                                <label @if ($blocked) aria-disabled="true" @endif><input type="checkbox" name="checks[]" value="{{ $key }}" @disabled($blocked) @checked(in_array($key, old('checks', []), true))><span>{{ $label }}@if ($blocked) <span class="ix-hint">— not confirmed by code</span>@endif</span></label>
                            @endforeach
                        </fieldset>
                        <div class="ix-field">
                            <label class="ix-label" for="note-approve">Internal note (reviewers only)</label>
                            <textarea id="note-approve" name="note" class="ix-textarea" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div><button type="submit" class="ix-btn ix-btn--primary">Verify the business</button></div>
                    </form>
                @endif

                @if ($isStanding && $v->status === \App\Models\BusinessVerification::UNDER_REVIEW)
                    <form method="post" action="{{ route('moderation.verifications.decide', [$v, 'reconfirm']) }}" class="ix-form">
                        @csrf
                        <p class="ix-hint">The business changed details it was verified with ({{ optional($v->events->where('event', 'identity_changed')->last())->note ?? 'see history' }}). Confirm the new details still describe the same business, or suspend or revoke below.</p>
                        <div><button type="submit" class="ix-btn ix-btn--primary">The new details are fine — restore the plate</button></div>
                    </form>
                @endif

                @if ($v->status === \App\Models\BusinessVerification::SUSPENDED)
                    <form method="post" action="{{ route('moderation.verifications.decide', [$v, 'reinstate']) }}">@csrf<button type="submit" class="ix-btn ix-btn--primary">Reinstate</button></form>
                @endif

                @php
                    $messageActions = array_filter([
                    in_array($v->status, [\App\Models\BusinessVerification::PENDING, \App\Models\BusinessVerification::UNDER_REVIEW], true) && ! $isStanding ? ['info', 'Ask for more information', 'ix-btn'] : null,
                    in_array($v->status, \App\Models\BusinessVerification::OPEN, true) && ! $isStanding ? ['reject', 'Do not approve', 'ix-btn ix-btn--danger'] : null,
                    $isStanding && in_array($v->status, [\App\Models\BusinessVerification::VERIFIED, \App\Models\BusinessVerification::UNDER_REVIEW], true) ? ['suspend', 'Suspend', 'ix-btn'] : null,
                    $isStanding && in_array($v->status, [\App\Models\BusinessVerification::VERIFIED, \App\Models\BusinessVerification::SUSPENDED, \App\Models\BusinessVerification::UNDER_REVIEW], true) ? ['revoke', 'Revoke', 'ix-btn ix-btn--danger'] : null,
                ]);
                @endphp
                @foreach ($messageActions as [$action, $label, $class])
                    <details>
                        <summary class="{{ $class }}">{{ $label }}</summary>
                        <form method="post" action="{{ route('moderation.verifications.decide', [$v, $action]) }}" class="ix-form ix-vgap">
                            @csrf
                            <div class="ix-field">
                                <label class="ix-label" for="msg-{{ $action }}">Message to the business (they see this)</label>
                                <textarea id="msg-{{ $action }}" name="message" class="ix-textarea" rows="3" minlength="10" maxlength="2000" required></textarea>
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" for="note-{{ $action }}">Internal note (reviewers only)</label>
                                <textarea id="note-{{ $action }}" name="note" class="ix-textarea" rows="2" maxlength="2000"></textarea>
                            </div>
                            <div><button type="submit" class="{{ $class }}">{{ $label }}</button></div>
                        </form>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    <section class="ix-panel" aria-labelledby="hist-h">
        <div class="ix-panel__head"><h2 id="hist-h">History</h2></div>
        <div class="ix-panel__body">
            <ol class="ix-vhist">
                @foreach ($v->events->sortByDesc('id') as $e)
                    <li>
                        <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->format('j M Y, H:i') }}</time>
                        <span>{{ $eventLabels[$e->event] ?? $e->event }} @if ($e->actor)— {{ $e->actor->name }}@elseif (in_array($e->event, ['expired', 'identity_changed', 'evidence_purged'], true))— automatic @endif
                            @if ($e->note)<span class="ix-vhist__note">{{ $e->note }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
</x-app-layout>
