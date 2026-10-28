<x-app-layout>
    @php
        $total = $questions->count();
        $waiting = $questions->filter(fn ($q) => (int) $q->status !== 1);
        $solved = $questions->filter(fn ($q) => (int) $q->status === 1);
        $team = auth()->user()->currentTeam;
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Questions</h1>
            <p class="ix-lede">What the team is stuck on, and what has already been answered. Ask in plain words; anyone who knows can reply.</p>
        </div>
    </div>

    <form class="ix-askbar" action="{{ route('seek') }}" method="GET" role="search">
        <label for="askbar-q" class="ix-askbar__label">Ask</label>
        <input id="askbar-q" name="question" type="text" maxlength="255" class="ix-askbar__input" placeholder="{{ $industry['ask_title'] }}" autocomplete="off">
        <button type="submit" class="ix-askbar__go">Ask a question <x-ix.arrow /></button>
    </form>

    @include('topics._filter', ['route' => route('questions')])

    @if ($total > 0)
        <div class="ix-board" x-data="{ show: 'all', q: '' }">
            <div class="ix-board__main">
                <div class="ix-board__bar">
                    <div class="ix-filters" role="group" aria-label="Filter questions" style="margin:0">
                        <button type="button" class="ix-pill" :aria-pressed="show === 'all'" @click="show = 'all'">All <span class="ix-pill__n">{{ $total }}</span></button>
                        <button type="button" class="ix-pill" :aria-pressed="show === 'open'" @click="show = 'open'">Waiting <span class="ix-pill__n">{{ $waiting->count() }}</span></button>
                        <button type="button" class="ix-pill" :aria-pressed="show === 'done'" @click="show = 'done'">Solved <span class="ix-pill__n">{{ $solved->count() }}</span></button>
                    </div>
                    <label class="ix-board__find">
                        <span class="ix-sr">Filter these questions</span>
                        <span class="material-symbols-rounded" aria-hidden="true">filter_list</span>
                        <input type="search" x-model="q" placeholder="Filter these questions">
                    </label>
                </div>

                <ul class="ix-qlist">
                    @foreach ($waiting->concat($solved) as $question)
                        @php($isSolved = (int) $question->status === 1)
                        @php($replies = $question->comments_count + $question->answers_count)
                        <li class="ix-q {{ $isSolved ? 'is-solved' : '' }}"
                            x-show="(show === 'all' || show === '{{ $isSolved ? 'done' : 'open' }}') && (q === '' || @js(strtolower($question->question.' '.$question->description)).includes(q.toLowerCase()))">
                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-q__count" aria-label="{{ $replies }} {{ Str::plural('reply', $replies) }}">
                                <span class="ix-stencil-num">{{ str_pad((string) min($replies, 99), 2, '0', STR_PAD_LEFT) }}</span>
                                <small>{{ Str::plural('reply', $replies) }}</small>
                            </a>
                            <div class="ix-q__body">
                                <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-q__title">{{ $question->question }}</a>
                                @if (filled($question->description))<p class="ix-q__ex">{{ Str::limit($question->description, 160) }}</p>@endif
                                <p class="ix-q__meta">
                                    <span class="ix-ref">{{ \App\Support\Reference::for($question) }}</span>
                                    <span>{{ $question->user->name ?? 'Former member' }}</span>
                                    <span>{{ $question->created_at->diffForHumans() }}</span>
                                    <span>{{ $question->isPublic() ? 'Everyone' : 'Your company' }}</span>
                                </p>
                                @include('topics._chips', ['item' => $question])
                            </div>
                            <span class="ix-q__state {{ $isSolved ? 'is-done' : 'is-open' }}">{{ $isSolved ? 'Solved' : 'Waiting' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <aside class="ix-board__rail">
                <div class="ix-tally" aria-label="Tally">
                    <div><span class="ix-stencil-num">{{ str_pad((string) $waiting->count(), 2, '0', STR_PAD_LEFT) }}</span><small>waiting for an answer</small></div>
                    <div><span class="ix-stencil-num">{{ str_pad((string) $solved->count(), 2, '0', STR_PAD_LEFT) }}</span><small>solved</small></div>
                </div>
                <section class="ix-ideas" aria-label="Ideas">
                    <h2 class="ix-ideas__head">Ideas for {{ $industry['label'] }}</h2>
                    <ul>
                        @foreach (array_slice($industry['questions'], 0, 5) as $idea)
                            <li><a class="ix-idea" href="{{ route('seek', ['question' => $idea]) }}">{{ $idea }}</a></li>
                        @endforeach
                    </ul>
                </section>
            </aside>
        </div>
    @elseif ($filterSlug !== '')
        <section class="ix-empty">
            <h2>{{ $filterSlug === 'none' ? 'Every question you can see has a topic' : ($activeTopic ? 'No questions under '.$activeTopic->name.' yet' : 'There is no topic with that address') }}</h2>
            <p>{{ $activeTopic ? 'Nothing has been asked under this topic, or nothing you can see.' : 'Pick a topic from the list, or clear the filter to see every question.' }}</p>
            <a href="{{ route('questions') }}" class="ix-btn">Show every question</a>
            @if ($activeTopic)<a href="{{ route('seek', ['topic' => $activeTopic->slug]) }}" class="ix-btn ix-btn--primary">Ask the first one</a>@endif
        </section>
    @else
        <section class="ix-empty ix-empty--start">
            <h2>{{ $team?->name ?? 'Your team' }} has no questions yet</h2>
            <p>Questions are how the team gets unblocked. Start with one that is true for a {{ strtolower($industry['label']) }} business, or write your own.</p>
            <ul class="ix-ideas__list">
                @foreach (array_slice($industry['questions'], 0, 4) as $idea)
                    <li><a class="ix-idea" href="{{ route('seek', ['question' => $idea]) }}">{{ $idea }}</a></li>
                @endforeach
            </ul>
            <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask the first question</a>
        </section>
    @endif
</x-app-layout>
