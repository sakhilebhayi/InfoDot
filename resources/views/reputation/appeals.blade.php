<x-app-layout>
    @php($flash = session('reputation_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Score appeals</h1>
            <p class="ix-lede">People who think their knowledge score is wrong. The score is worked out by fixed rules from visible work, so check the evidence, recalculate, and tell them what you found.</p>
        </div>
    </div>
    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif
    @forelse ($appeals as $a)
        @php($r = $engine->compute($a->user))
        <section style="margin-bottom: 20px;">
            <h2 class="ix-label">{{ $a->user->name }} <span class="ix-hint">{{ $a->created_at->format('j M Y') }}</span></h2>
            <p>{{ $a->message }}</p>
            <p class="ix-hint">Now: {{ $r['tier_name'] }}, {{ $r['total'] }} points. @foreach ($r['dimensions'] as $d){{ $d['label'] }} {{ $d['score'] }}@if(!$loop->last), @endif @endforeach</p>
            <form method="POST" action="{{ route('moderation.reputation.resolve', $a) }}" class="ix-form">
                @csrf
                <div class="ix-field">
                    <label class="ix-label" for="note-{{ $a->id }}">What you found</label>
                    <textarea class="ix-textarea" id="note-{{ $a->id }}" name="note" rows="2" maxlength="1000" required></textarea>
                </div>
                <button class="ix-btn ix-btn--primary" type="submit">Recalculate and reply</button>
            </form>
        </section>
    @empty
        <section class="ix-empty"><h2>No appeals waiting</h2><p>Nobody has questioned their score.</p></section>
    @endforelse
</x-app-layout>
