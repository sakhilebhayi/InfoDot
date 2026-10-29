<x-app-layout>
    @php($flash = session('seminar_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Seminar reports</h1>
            <p class="ix-lede">Reports from signed-in people about seminars. Removing a seminar cancels it, tells everyone who registered, refunds anyone who paid in full, and tells the host why. The host is never told who reported.</p>
        </div>
    </div>
    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif
    @error('reason')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
    @error('appeal')<p class="ix-error" role="alert">{{ $message }}</p>@enderror

    @if ($appeals->isNotEmpty())
        <h2 class="ix-label">Appeals against a removal</h2>
        @foreach ($appeals as $a)
            <section style="margin-bottom: 20px;">
                <p><a href="{{ route('seminars.show', $a->seminar) }}" style="text-decoration: underline;">{{ $a->seminar->title }}</a> <span class="ix-hint">starts {{ $a->seminar->starts_at->diffForHumans() }}</span></p>
                <p class="ix-hint">Removed for: {{ $a->seminar->removed_reason }}</p>
                <p style="max-width: 70ch;">{{ $a->message }}</p>
                <form method="POST" action="{{ route('moderation.seminars.appeal', $a) }}" class="ix-form" style="max-width: 520px;">@csrf
                    <label class="ix-label" for="ap-{{ $a->id }}">Your decision and why (the host sees it)</label>
                    <input class="ix-input" id="ap-{{ $a->id }}" name="note" type="text" minlength="5" maxlength="500" required>
                    <div style="display: flex; gap: 8px;">
                        @if ($a->seminar->starts_at->isFuture())<button class="ix-btn" type="submit" name="decision" value="reinstate">Put it back on</button>@endif
                        <button class="ix-btn ix-btn--danger" type="submit" name="decision" value="deny">Deny the appeal</button>
                    </div>
                    @if (! $a->seminar->starts_at->isFuture())<p class="ix-hint">It has started, so it can only be denied.</p>@endif
                </form>
            </section>
        @endforeach
    @endif

    @forelse ($reports as $seminarId => $rows)
        @php($seminar = $rows->first()->seminar)
        <section style="margin-bottom: 24px;">
            <h2 class="ix-label"><a href="{{ route('seminars.show', $seminar) }}" style="text-decoration: underline;">{{ $seminar->title }}</a> <span class="ix-hint">{{ $rows->count() }} {{ $rows->count() === 1 ? 'report' : 'reports' }} · {{ $seminar->status }}</span></h2>
            <ul class="ix-list">
                @foreach ($rows as $r)
                    <li style="padding: 10px 14px;">
                        @if ($r->about_name)<span class="ix-chip">About a person: {{ $r->about_name }}</span> @endif
                        <b>{{ \App\Models\SeminarReport::REASONS[$r->reason] ?? $r->reason }}</b> — {{ $r->reporter->name }}@if ($r->detail): {{ $r->detail }}@endif
                        @if ($r->about_name)
                            @forelse ($registrants($seminar, $r->about_name) as $who)
                                <form method="POST" action="{{ route('moderation.seminars.bar', [$seminar, $who->id]) }}" style="display: flex; gap: 6px; margin-top: 6px;">@csrf
                                    <input class="ix-input" name="note" type="text" minlength="5" maxlength="500" placeholder="Why {{ $who->name }} is barred from the room" required>
                                    <button class="ix-btn ix-btn--sm ix-btn--danger" type="submit">Bar {{ $who->name }}</button>
                                </form>
                            @empty
                                <span class="ix-hint">No registered person matches that name.</span>
                            @endforelse
                        @endif
                        <form method="POST" action="{{ route('moderation.seminars.dismiss', $r) }}" style="display: flex; gap: 6px; margin-top: 6px;">@csrf
                            <input class="ix-input" name="note" type="text" minlength="5" maxlength="500" placeholder="Why this report is dismissed" required>
                            <button class="ix-btn ix-btn--sm" type="submit">Dismiss</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            @if ($seminar->removed_at === null && $seminar->status !== 'cancelled')
                @include('seminars._remove-form', ['seminar' => $seminar, 'idPrefix' => 'mod'])
            @endif
        </section>
    @empty
        <section class="ix-empty"><h2>No open reports</h2><p>Nobody has reported a seminar.</p></section>
    @endforelse

    @if ($recent->isNotEmpty())
        <h2 class="ix-label">Recently decided</h2>
        <ul class="ix-list">
            @foreach ($recent as $r)
                <li style="padding: 10px 14px;">{{ $r->seminar->title }} — {{ $r->status }}@if ($r->resolution_note): {{ $r->resolution_note }}@endif <span class="ix-ref">{{ $r->resolved_at?->diffForHumans() }}</span></li>
            @endforeach
        </ul>
    @endif
</x-app-layout>
