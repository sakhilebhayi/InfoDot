<x-app-layout>
    @php
        $total = $questions->count();
        $open = $questions->filter(fn ($q) => (int) $q->status !== 1)->count();
        $done = $total - $open;
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Questions</h1>
            <p class="ix-lede">Ask your team what is blocking you. Mark a question solved when an answer worked.</p>
        </div>
        <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">
            <span class="material-symbols-rounded" aria-hidden="true">add</span>Ask a question
        </a>
    </div>

    @if ($total > 0)
        <div x-data="{ show: 'all' }">
            <div class="ix-filters" role="group" aria-label="Filter questions">
                <button type="button" class="ix-pill" :aria-pressed="show === 'all'" @click="show = 'all'">All <span class="ix-pill__n">{{ $total }}</span></button>
                <button type="button" class="ix-pill" :aria-pressed="show === 'open'" @click="show = 'open'">Unsolved <span class="ix-pill__n">{{ $open }}</span></button>
                <button type="button" class="ix-pill" :aria-pressed="show === 'done'" @click="show = 'done'">Solved <span class="ix-pill__n">{{ $done }}</span></button>
            </div>

            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead>
                        <tr><th>Question</th><th>Status</th><th>Asked by</th><th class="num">Replies</th><th>Asked</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($questions as $question)
                        @php($isSolved = (int) $question->status === 1)
                        <tr x-show="show === 'all' || show === '{{ $isSolved ? 'done' : 'open' }}'">
                            <td>
                                <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="title">{{ $question->question }}</a>
                                @if (filled($question->description))<p class="sub">{{ $question->description }}</p>@endif
                            </td>
                            <td><span class="ix-chip {{ $isSolved ? 'ix-chip--done' : 'ix-chip--open' }}">{{ $isSolved ? 'Solved' : 'Unsolved' }}</span></td>
                            <td class="muted">{{ $question->user->name ?? 'Former member' }}</td>
                            <td class="num">{{ $question->comments_count }}</td>
                            <td class="muted">{{ $question->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
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
