<x-app-layout>
    @php
        $total = $questions->count();
        $open = $questions->filter(fn ($q) => (int) $q->status !== 1)->count();
        $done = $total - $open;
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Questions</h1>
            <p class="ix-lede">Ask your team what is blocking you. Mark a question solved when the answer worked.</p>
        </div>
        <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">
            <span class="material-symbols-rounded" aria-hidden="true">add</span>Ask a question
        </a>
    </div>

    @if ($total > 0)
        <div class="ix-state-bar">
            <div class="ix-state-bar__verdict">
                <span class="ix-lamp {{ $open > 0 ? 'ix-lamp--signal' : 'ix-lamp--ok' }}" aria-hidden="true"></span>
                @if ($open > 0)
                    {{ $open }} {{ Str::plural('question', $open) }} still {{ $open === 1 ? 'needs' : 'need' }} an answer
                @else
                    Every question has been answered
                @endif
            </div>
            <div class="ix-readouts">
                <div class="ix-readout"><span class="ix-readout__label">Unsolved</span><x-ix.figure :value="$open" /></div>
                <div class="ix-readout"><span class="ix-readout__label">Solved</span><x-ix.figure :value="$done" /></div>
                <div class="ix-readout"><span class="ix-readout__label">Total</span><x-ix.figure :value="$total" /></div>
            </div>
        </div>

        <div class="ix-list">
            @foreach ($questions as $question)
                @php($isSolved = (int) $question->status === 1)
                <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-row">
                    <span><span class="ix-state {{ $isSolved ? 'ix-state--done' : 'ix-state--open' }}">{{ $isSolved ? 'Solved' : 'Unsolved' }}</span></span>
                    <span>
                        <h2 class="ix-row__title">{{ $question->question }}</h2>
                        @if (filled($question->description))
                            <p class="ix-row__excerpt">{{ $question->description }}</p>
                        @else
                            <p class="ix-row__excerpt ix-row__excerpt--none">No description.</p>
                        @endif
                        <p class="ix-row__by">{{ $question->user->name ?? 'Former member' }} &middot; {{ $question->created_at->diffForHumans() }}</p>
                    </span>
                    <span class="ix-row__figs">
                        <span class="ix-count"><x-ix.figure :value="$question->comments_count" /><small>{{ Str::plural('reply', $question->comments_count) }}</small></span>
                        <span class="ix-count"><x-ix.figure :value="$question->likes_count" /><small>{{ Str::plural('like', $question->likes_count) }}</small></span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <section class="ix-empty">
            <h2>No questions yet</h2>
            <p>Questions are how the team gets unblocked. Describe what you are trying to do and what is stopping you, and anyone on the team can answer.</p>
            <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask the first question</a>
        </section>
    @endif
</x-app-layout>
