<x-app-layout :title="$team->name.' standing'">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Standing on InfoDot</h1>
            <p class="ix-lede">Reviews of <b>{{ $team->name }}</b> InfoDot has told you about, decisions you can appeal, and your appeals. An appeal is decided by a reviewer who did not make the original decision.</p>
        </div>
        <a href="{{ route('business.edit', $team) }}" class="ix-btn ix-btn--ghost">Edit profile</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    @if ($team->isRestricted())
        <div class="ix-previewbar" role="status"><p><b>{{ $team->name }} is out of the directory while InfoDot reviews it.</b> Its page opens only for people in your company; everyone else finds no page, and is never given a reason.</p></div>
    @endif

    <section class="ix-panel" aria-labelledby="rev-h">
        <div class="ix-panel__head"><h2 id="rev-h">Reviews</h2></div>
        <div class="ix-panel__body">
            @if ($investigations->isEmpty())
                <p class="ix-hint">No review of {{ $team->name }} needs your attention.</p>
            @else
                <ol class="ix-ledger">
                    @foreach ($investigations as $inv)
                        <li>
                            <time datetime="{{ $inv->created_at->toIso8601String() }}">{{ $inv->created_at->format('j M Y') }}</time>
                            <span>
                                <b>{{ $inv->isOpen() ? 'InfoDot is reviewing the business' : $inv->statusLabel() }}</b>@if ($inv->restricted) — out of the directory meanwhile @endif
                                @if ($inv->message_to_business)<span class="ix-standing__msg">{{ $inv->message_to_business }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>

    @if ($verifications->isNotEmpty())
        <section class="ix-panel ix-vgap" aria-labelledby="ver-h">
            <div class="ix-panel__head"><h2 id="ver-h">Verification decisions</h2></div>
            <div class="ix-panel__body">
                <ol class="ix-ledger">
                    @foreach ($verifications as $v)
                        <li><time datetime="{{ $v->updated_at->toIso8601String() }}">{{ $v->updated_at->format('j M Y') }}</time><span><b>{{ $v->label() }}</b> <a href="{{ route('business.verification', $team) }}">Details on the verification page</a></span></li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="ix-panel ix-vgap" aria-labelledby="app-h">
        <div class="ix-panel__head"><h2 id="app-h">Appeals</h2></div>
        <div class="ix-panel__body">
            @if ($appeals->isNotEmpty())
                <ol class="ix-ledger">
                    @foreach ($appeals as $a)
                        <li>
                            <time datetime="{{ $a->created_at->toIso8601String() }}">{{ $a->created_at->format('j M Y') }}</time>
                            <span><b>{{ \App\Models\BusinessAppeal::STATUSES[$a->status] }}</b> — appeal against a {{ $a->subject_type === 'investigation' ? 'review outcome' : 'verification decision' }}
                                @if ($a->decision_note)<span class="ix-standing__msg">{{ $a->decision_note }}</span>@endif
                                @if ($a->status === 'upheld' && $a->subject_type === 'verification')<span class="ix-hint">You can <a href="{{ route('business.verification', $team) }}">apply for verification again</a>; reviewers will see this decision.</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if (empty($appealable))
                <p class="ix-hint ix-vgap">There is no decision you can appeal right now. Each decision can be appealed once.</p>
            @else
                <form method="post" action="{{ route('business.appeal', $team) }}" enctype="multipart/form-data" class="ix-form ix-vgap">
                    @csrf
                    <h3 class="ix-dir__h2">Appeal a decision</h3>
                    <div class="ix-form__row">
                        <label class="ix-label" for="subject">Decision</label>
                        <select class="ix-input" id="subject" name="subject" required>
                            @foreach ($appealable as $s)
                                @if ($s instanceof \App\Models\BusinessInvestigation)
                                    <option value="investigation:{{ $s->id }}">{{ $s->isOpen() ? 'Being kept out of the directory during a review' : $s->statusLabel() }} ({{ $s->updated_at->format('j M Y') }})</option>
                                @else
                                    <option value="verification:{{ $s->id }}">Verification {{ mb_strtolower($s->label()) }} ({{ $s->updated_at->format('j M Y') }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="ix-form__row"><label class="ix-label" for="body">Why it is wrong</label><textarea class="ix-textarea" id="body" name="body" rows="6" minlength="30" maxlength="3000" required placeholder="What the reviewer missed. If you share branding with a related business (a franchise, a branch), say so and how.">{{ old('body') }}</textarea></div>
                    <div class="ix-form__row"><label class="ix-label" for="files">Evidence <span class="ix-hint">optional; up to {{ \App\Services\Trust\BusinessTrustService::MAX_FILES }} files. Only reviewers see them.</span></label><input class="ix-input" type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></div>
                    <div><button type="submit" class="ix-btn ix-btn--primary">Send the appeal</button></div>
                </form>
            @endif
        </div>
    </section>
</x-app-layout>
