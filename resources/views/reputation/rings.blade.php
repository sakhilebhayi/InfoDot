<x-app-layout>
    @php($flash = session('rings_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Account rings</h1>
            <p class="ix-lede">Groups of accounts that accept each other's answers far more than ordinary help would. A flag is a question, not a verdict: it bans nobody and hides nothing, and the people in it are not told. While it is open or confirmed, acceptances between its members count {{ rtrim(rtrim(number_format($discount, 2), '0'), '.') }} times as much in the knowledge score. Dismissing it removes that.</p>
        </div>
    </div>

    <nav class="ix-mod__tabs" aria-label="Flag status">
        @foreach (['open' => 'Open', 'confirmed' => 'Confirmed', 'dismissed' => 'Dismissed'] as $key => $label)
            <a href="{{ route('moderation.rings', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }} <span class="ix-mod__n">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>

    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif
    @error('note')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
    @error('decision')<p class="ix-error" role="alert">{{ $message }}</p>@enderror

    @forelse ($flags as $flag)
        <section style="margin-bottom: 24px;" aria-labelledby="flag-{{ $flag->id }}">
            <h2 class="ix-label" id="flag-{{ $flag->id }}">
                {{ $flag->kind === 'cycle' ? 'Circle of '.count($flag->member_ids).' accounts' : 'Tight group of '.count($flag->member_ids).' accounts' }}
                <span class="ix-hint">first seen {{ $flag->detected_at->format('j M Y') }}</span>
            </h2>
            <p>{{ collect($flag->member_ids)->map(fn ($id) => $names[$id] ?? 'Removed account')->implode(', ') }}</p>
            <p class="ix-hint">
                {{ $flag->kind === 'cycle' ? 'Each one accepted an answer from the next, and the last from the first.' : 'Every one of them has accepted answers from another member, and most of the help each received came from inside the group.' }}
                {{ $flag->edge_count }} acceptances between them; {{ round($flag->internal_ratio * 100) }}% of all the acceptances they received came from each other.
            </p>
            @if ($flag->status !== 'open')
                <p class="ix-hint"><span class="ix-chip {{ $flag->status === 'confirmed' ? 'ix-chip--open' : 'ix-chip--done' }}">{{ ucfirst($flag->status) }}</span> by {{ $flag->reviewer?->name ?? 'a moderator' }}, {{ $flag->reviewed_at?->format('j M Y') }}: {{ $flag->review_note }}</p>
            @endif
            <form method="POST" action="{{ route('moderation.rings.review', $flag) }}" class="ix-form">
                @csrf
                <div class="ix-field">
                    <label class="ix-label" for="note-{{ $flag->id }}">What you found (required)</label>
                    <textarea class="ix-textarea" id="note-{{ $flag->id }}" name="note" rows="2" minlength="5" maxlength="1000" required></textarea>
                </div>
                <button class="ix-btn" type="submit" name="decision" value="dismissed" @disabled($flag->status === 'dismissed')>Dismiss: this is ordinary help</button>
                <button class="ix-btn ix-btn--primary" type="submit" name="decision" value="confirmed" @disabled($flag->status === 'confirmed')>Confirm: keep the lower weight</button>
            </form>
        </section>
    @empty
        <section class="ix-empty"><h2>Nothing {{ $status }}</h2><p>{{ $status === 'open' ? 'No group of accounts is waiting for a look.' : 'No flag has been '.$status.' yet.' }}</p></section>
    @endforelse
</x-app-layout>
