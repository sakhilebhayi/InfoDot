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

    @forelse ($reports as $seminarId => $rows)
        @php($seminar = $rows->first()->seminar)
        <section style="margin-bottom: 24px;">
            <h2 class="ix-label"><a href="{{ route('seminars.show', $seminar) }}" style="text-decoration: underline;">{{ $seminar->title }}</a> <span class="ix-hint">{{ $rows->count() }} {{ $rows->count() === 1 ? 'report' : 'reports' }} · {{ $seminar->status }}</span></h2>
            <ul class="ix-list">
                @foreach ($rows as $r)
                    <li style="padding: 10px 14px;">
                        <b>{{ \App\Models\SeminarReport::REASONS[$r->reason] ?? $r->reason }}</b> — {{ $r->reporter->name }}@if ($r->detail): {{ $r->detail }}@endif
                        <form method="POST" action="{{ route('moderation.seminars.dismiss', $r) }}" style="display: flex; gap: 6px; margin-top: 6px;">@csrf
                            <input class="ix-input" name="note" type="text" minlength="5" maxlength="500" placeholder="Why this report is dismissed" required>
                            <button class="ix-btn ix-btn--sm" type="submit">Dismiss</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            @if ($seminar->removed_at === null && $seminar->status !== 'cancelled')
                <form method="POST" action="{{ route('moderation.seminars.remove', $seminar) }}" class="ix-form" style="margin-top: 8px;" onsubmit="return confirm('Remove this seminar? Everyone who registered is told and anyone who paid is refunded.');">@csrf
                    <label class="ix-label" for="why-{{ $seminar->id }}">Reason (the host sees this)</label>
                    <input class="ix-input" id="why-{{ $seminar->id }}" name="reason" type="text" minlength="10" maxlength="500" required>
                    <button class="ix-btn ix-btn--danger" type="submit">Remove the seminar</button>
                </form>
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
