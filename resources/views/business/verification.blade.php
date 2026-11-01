<x-app-layout>
    @php
        $focus = $open ?? $standing ?? $latest;
        $state = $focus?->status;
        [$mark, $tone, $headline, $explain] = match (true) {
            $standing?->isCurrent() && ! $open => ['Verified', 'ok', 'InfoDot verified '.$team->name, 'The approval plate is on the business page. Review is due by '.$standing->expires_at->format('j F Y').'; renewal opens a month before.'],
            $state === \App\Models\BusinessVerification::PENDING => ['Submitted', 'wait', 'Your application is with InfoDot', 'A reviewer who is not part of your business will look at it. You can still add documents or confirm an official email meanwhile.'],
            $state === \App\Models\BusinessVerification::UNDER_REVIEW && $focus->verified_at === null => ['In review', 'wait', 'A reviewer is looking at your application', 'You can still add documents. You will hear in your notices when there is a decision.'],
            $state === \App\Models\BusinessVerification::UNDER_REVIEW => ['In review', 'wait', 'Your verified details changed', 'The business changed its name, registration details or website after it was verified, so the plate is off until a reviewer confirms the new details. Nothing is needed from you unless InfoDot asks.'],
            $state === \App\Models\BusinessVerification::INFO_REQUIRED => ['More info', 'wait', 'InfoDot needs more from you', 'Read the note below, add what is asked for, then send the application back.'],
            $state === \App\Models\BusinessVerification::SUSPENDED => ['Suspended', 'stop', 'Verification is suspended', 'The plate is off while InfoDot looks into something. The reason is below.'],
            $state === \App\Models\BusinessVerification::REVOKED => ['Revoked', 'stop', 'Verification was withdrawn', 'The reason is below. You can apply again with fresh evidence.'],
            $state === \App\Models\BusinessVerification::REJECTED => ['Not approved', 'stop', 'The last application was not approved', 'The reason is below. You can apply again with what was missing.'],
            $state === \App\Models\BusinessVerification::EXPIRED => ['Lapsed', 'muted', 'Verification has lapsed', 'Verification lasts a year so it stays true. Apply again to renew it.'],
            default => ['Unverified', 'muted', $team->name.' is not verified yet', 'Verification shows people on InfoDot that this account really is the business it says it is.'],
        };
        $toneClass = ['ok' => 'ix-vstate__mark--ok', 'wait' => 'ix-vstate__mark--wait', 'stop' => 'ix-vstate__mark--stop', 'muted' => ''][$tone];
        $sections = array_values(array_filter([
            $open ? ['application', 'Your application'] : null,
            $open ? ['email', 'Official email'] : null,
            $canApply ? ['meaning', 'What verification means'] : null,
            $canApply ? ['apply', ($standing ? 'Apply again' : 'Apply')] : null,
            ['history', 'History'],
        ]));
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Verification</h1>
            <p class="ix-lede">Evidence-based verification of <b>{{ $team->name }}</b> by InfoDot. <a href="{{ route('business.show', $team) }}">Open the business page</a>.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="ix-vstate" aria-labelledby="vstate-h">
        <span class="ix-vstate__mark {{ $toneClass }}" aria-hidden="true">{{ $mark }}</span>
        <div>
            <h2 id="vstate-h">{{ $headline }}</h2>
            <p>{{ $explain }}</p>
        </div>
        @if ($focus?->message_to_applicant && in_array($state, [\App\Models\BusinessVerification::INFO_REQUIRED, \App\Models\BusinessVerification::REJECTED, \App\Models\BusinessVerification::SUSPENDED, \App\Models\BusinessVerification::REVOKED], true))
            <p class="ix-vstate__msg"><b>From the InfoDot reviewer</b>{{ $focus->message_to_applicant }}</p>
        @endif
    </section>

    <div class="ix-settings">
        <nav class="ix-settings__index" aria-label="Verification sections">
            @foreach ($sections as $i => [$id, $label])
                <a href="#{{ $id }}"><span class="ix-settings__no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ix-settings__body">
            @if ($open)
                <section id="application" class="ix-settings__sec">
                    <h2>Your application</h2>
                    <dl class="ix-biz__facts">
                        <div><dt>Registered name</dt><dd>{{ $open->registered_name }}</dd></div>
                        @if ($open->trading_name)<div><dt>Trading as</dt><dd>{{ $open->trading_name }}</dd></div>@endif
                        <div><dt>Kind</dt><dd>{{ \App\Models\BusinessVerification::CATEGORIES[$open->category] ?? $open->category }}</dd></div>
                        @if ($open->registration_number)<div><dt>Registration no.</dt><dd>{{ $open->registration_number }}</dd></div>@endif
                        <div><dt>Registered in</dt><dd>{{ $open->registration_country }}</dd></div>
                        @if ($open->website)<div><dt>Website</dt><dd>{{ $open->website }}</dd></div>@endif
                        <div><dt>Applied by</dt><dd>{{ $open->applicant?->name ?? '—' }}, {{ $open->applicant_role }}</dd></div>
                        <div><dt>Submitted</dt><dd>{{ $open->created_at->format('j F Y') }}</dd></div>
                    </dl>

                    <h3 class="ix-vsub">Documents</h3>
                    <ul class="ix-evidence">
                        @foreach ($open->evidence as $doc)
                            <li>
                                <span class="material-symbols-rounded" aria-hidden="true">{{ $doc->kind === 'pdf' ? 'description' : 'image' }}</span>
                                <span class="ix-evidence__name">{{ $doc->name }}<small>{{ $doc->caption }}</small></span>
                                <a href="{{ route('attachments.show', $doc) }}" class="ix-btn ix-btn--ghost ix-btn--sm" target="_blank" rel="noopener">Open</a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($open->evidence->count() < config('verification.max_evidence_files'))
                        <form method="post" action="{{ route('business.verification.evidence', $team) }}" enctype="multipart/form-data" class="ix-form ix-vgap">
                            @csrf
                            <div class="ix-evrow">
                                <div class="ix-field">
                                    <label class="ix-label" for="add-label">What it is</label>
                                    <select id="add-label" name="evidence[0][label]" class="ix-input">
                                        @foreach ($labels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="ix-field">
                                    <label class="ix-label" for="add-file">Document (PDF or image)</label>
                                    <input id="add-file" type="file" name="evidence[0][file]" accept="application/pdf,image/jpeg,image/png,image/webp" required class="ix-input">
                                </div>
                            </div>
                            <div><button type="submit" class="ix-btn">Add the document</button></div>
                        </form>
                    @endif

                    <div class="ix-vbtns">
                        @if ($open->status === \App\Models\BusinessVerification::INFO_REQUIRED)
                            <form method="post" action="{{ route('business.verification.resubmit', $team) }}">@csrf<button type="submit" class="ix-btn ix-btn--primary">Send it back to InfoDot</button></form>
                        @endif
                        <form method="post" action="{{ route('business.verification.withdraw', $team) }}" onsubmit="return confirm('Withdraw this application? You can apply again later.');">@csrf<button type="submit" class="ix-btn ix-btn--ghost">Withdraw the application</button></form>
                    </div>
                </section>

                <section id="email" class="ix-settings__sec">
                    <h2>Official email</h2>
                    @if ($open->official_email_confirmed_at)
                        <p><span class="ix-chip ix-chip--done">Confirmed</span> The business controls <b>{{ $open->official_email }}</b> (confirmed {{ $open->official_email_confirmed_at->format('j M Y') }}).</p>
                    @else
                        <p class="ix-hint">Optional, and one of the checks a reviewer can count. We send a code to an address at the business’s own website domain{{ $open->websiteHost() ? ' ('.$open->websiteHost().')' : '' }}. Gmail, Outlook and other free mailboxes cannot show that.</p>
                        <form method="post" action="{{ route('business.verification.code', $team) }}" class="ix-vcode">
                            @csrf
                            <div class="ix-field">
                                <label class="ix-label" for="official_email">Address at the business’s domain</label>
                                <input id="official_email" type="email" name="official_email" class="ix-input" value="{{ old('official_email', $open->official_email) }}" placeholder="{{ $open->websiteHost() ? 'accounts@'.$open->websiteHost() : 'Add the website to your application first' }}" @disabled(! $open->websiteHost()) required>
                            </div>
                            <button type="submit" class="ix-btn" @disabled(! $open->websiteHost())>Send a code</button>
                        </form>
                        @if ($open->email_code_expires_at?->isFuture())
                            <form method="post" action="{{ route('business.verification.confirm', $team) }}" class="ix-vcode ix-vgap">
                                @csrf
                                <div class="ix-field">
                                    <label class="ix-label" for="code">Code from the email to {{ $open->official_email }}</label>
                                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="ix-input ix-vcode__digits" required>
                                </div>
                                <button type="submit" class="ix-btn ix-btn--primary">Confirm</button>
                            </form>
                        @endif
                    @endif
                </section>
            @endif

            @if ($canApply)
                <section id="meaning" class="ix-settings__sec">
                    <h2>What verification means</h2>
                    <p class="ix-biz__prose ix-vflush">A person at InfoDot who is not part of your business reviews what you send. Approval needs at least two confirmed checks, and one of them is always a review of registration or founding documents:</p>
                    <ul class="ix-trust__checks">
                        @foreach (\App\Models\BusinessVerification::CHECKS as $label)<li>{{ $label }}</li>@endforeach
                    </ul>
                    <p class="ix-hint ix-vgap">InfoDot does not check company registries or telephone numbers automatically — nothing like that is connected, so the plate never claims it. Verification lasts a year. Changing the business’s name, registration details or website sends it back for a quick review.</p>
                    <p class="ix-hint">Who sees your documents: only your business’s owner and administrators, and InfoDot reviewers. They are never public, and are deleted a year after an application is closed. Please do not send ID documents unless a reviewer asks.</p>
                </section>

                <section id="apply" class="ix-settings__sec">
                    <h2>{{ $standing ? 'Apply again' : 'Apply' }}</h2>
                    <form method="post" action="{{ route('business.verification.store', $team) }}" enctype="multipart/form-data" class="ix-form">
                        @csrf
                        <div class="ix-form__row">
                            <div class="ix-field">
                                <label class="ix-label" for="category">What kind of business</label>
                                <select id="category" name="category" class="ix-input" required>
                                    @foreach (\App\Models\BusinessVerification::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" for="registration_country">Registered in</label>
                                <input id="registration_country" name="registration_country" class="ix-input" maxlength="60" required value="{{ old('registration_country', $profile?->registration_country ?? 'South Africa') }}">
                            </div>
                        </div>
                        <div class="ix-form__row">
                            <div class="ix-field">
                                <label class="ix-label" for="registered_name">Registered name</label>
                                <input id="registered_name" name="registered_name" class="ix-input" maxlength="160" required value="{{ old('registered_name', $team->name) }}">
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" for="trading_name">Trading name, if different</label>
                                <input id="trading_name" name="trading_name" class="ix-input" maxlength="160" value="{{ old('trading_name', $profile?->trading_name) }}">
                            </div>
                        </div>
                        <div class="ix-form__row">
                            <div class="ix-field">
                                <label class="ix-label" for="registration_number">Registration number</label>
                                <input id="registration_number" name="registration_number" class="ix-input" maxlength="60" value="{{ old('registration_number', $profile?->registration_number) }}" placeholder="e.g. 2019/123456/07">
                                <p class="ix-hint">Needed for a registered company. Sole proprietors and informal businesses can leave it empty.</p>
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" for="website">Website</label>
                                <input id="website" type="url" name="website" class="ix-input" maxlength="255" value="{{ old('website', $profile?->website) }}" placeholder="https://">
                            </div>
                        </div>
                        <div class="ix-form__row">
                            <div class="ix-field">
                                <label class="ix-label" for="phone">Business telephone</label>
                                <input id="phone" name="phone" class="ix-input" maxlength="40" value="{{ old('phone', $profile?->contact_phone) }}">
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" for="address">Operating address</label>
                                <input id="address" name="address" class="ix-input" maxlength="500" value="{{ old('address', $profile?->location) }}">
                            </div>
                        </div>
                        <div class="ix-field">
                            <label class="ix-label" for="applicant_role">Your role at the business</label>
                            <input id="applicant_role" name="applicant_role" class="ix-input" maxlength="80" required value="{{ old('applicant_role') }}" placeholder="Director, owner, company secretary…">
                        </div>

                        <fieldset class="ix-field ix-vfieldset">
                            <legend class="ix-label">Documents (PDF or image, up to {{ config('verification.max_evidence_files') }})</legend>
                            <div class="ix-evrows">
                                @foreach ([['registration', true], ['authority', false], ['address', false]] as $i => [$label, $required])
                                    <div class="ix-evrow">
                                        <select name="evidence[{{ $i }}][label]" class="ix-input" aria-label="Document {{ $i + 1 }}: what it is">
                                            @foreach ($labels as $key => $text)<option value="{{ $key }}" @selected($key === $label)>{{ $text }}</option>@endforeach
                                        </select>
                                        <input type="file" name="evidence[{{ $i }}][file]" accept="application/pdf,image/jpeg,image/png,image/webp" class="ix-input" aria-label="Document {{ $i + 1 }}: file" @required($required)>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="ix-vdeclare">
                            <input type="checkbox" name="declaration" value="1" required>
                            <span>I am authorised to act for {{ $team->name }}, and what I send is true and belongs to this business.</span>
                        </label>

                        <div><button type="submit" class="ix-btn ix-btn--primary">Send the application</button></div>
                    </form>
                </section>
            @endif

            <section id="history" class="ix-settings__sec">
                <h2>History</h2>
                @php($events = $history->flatMap->events->sortByDesc('id')->take(30))
                @if ($events->isEmpty())
                    <p class="ix-hint">Nothing yet.</p>
                @else
                    <ol class="ix-vhist">
                        @foreach ($events as $e)
                            <li>
                                <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->format('j M Y, H:i') }}</time>
                                <span>{{ [
                                    'submitted' => 'Application sent', 'evidence_added' => 'Document added', 'info_provided' => 'Sent back with more information',
                                    'withdrawn' => 'Withdrawn', 'email_code_sent' => 'Code sent to the official email', 'email_confirmed' => 'Official email confirmed',
                                    'review_started' => 'A reviewer started', 'info_requested' => 'InfoDot asked for more information', 'approved' => 'Verified',
                                    'rejected' => 'Not approved', 'reconfirmed' => 'New details confirmed', 'suspended' => 'Suspended', 'reinstated' => 'Reinstated',
                                    'revoked' => 'Revoked', 'identity_changed' => 'Verified details changed — back for review', 'expired' => 'Lapsed',
                                    'superseded' => 'Replaced by a renewal', 'evidence_purged' => 'Documents deleted after the retention period',
                                ][$e->event] ?? $e->event }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
