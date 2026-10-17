<x-app-layout>
    @php
        $total = $questions->count();
        $open = $questions->filter(fn ($q) => (int) $q->status !== 1)->count();
        $done = $total - $open;
    @endphp

    @if ($total > 0)
        <div x-data="{ show: 'all' }">
            <div class="ix-filters" role="group" aria-label="Filter questions">
                <button type="button" class="ix-pill" :aria-pressed="show === 'all'" @click="show = 'all'">All <span class="ix-pill__n">{{ $total }}</span></button>
                <button type="button" class="ix-pill" :aria-pressed="show === 'open'" @click="show = 'open'">Unsolved <span class="ix-pill__n">{{ $open }}</span></button>
                <button type="button" class="ix-pill" :aria-pressed="show === 'done'" @click="show = 'done'">Solved <span class="ix-pill__n">{{ $done }}</span></button>
            </div>

            <div class="ix-list">
                @foreach ($questions as $question)
                    @php($isSolved = (int) $question->status === 1)
                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-row" x-show="show === 'all' || show === '{{ $isSolved ? 'done' : 'open' }}'">
                        <span class="ix-lead {{ $isSolved ? 'ix-lead--done' : '' }}" title="{{ $isSolved ? 'Solved' : 'Waiting for an answer' }}">
                            <span class="material-symbols-rounded" aria-hidden="true">{{ $isSolved ? 'check' : 'question_mark' }}</span>
                            <span class="ix-sr">{{ $isSolved ? 'Solved' : 'Unsolved' }}</span>
                        </span>
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
                            <span class="ix-go" aria-hidden="true"><span class="material-symbols-rounded">chevron_right</span></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <section class="ix-empty">
            <h2>No questions yet</h2>
            <p>Questions are how the team gets unblocked. Say what you are trying to do and what is stopping you, and anyone on the team can answer.</p>
            <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask the first question</a>
        </section>
    @endif
</x-app-layout>
