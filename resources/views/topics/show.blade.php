<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $topic->name }}</h1>
            @if (filled($topic->description))<p class="ix-lede">{{ $topic->description }}</p>@endif
            @unless ($topic->active)<p class="ix-hint">This topic is retired. What is filed under it stays, but new questions and solutions cannot choose it.</p>@endunless
        </div>
        @if ($topic->active)
            <a href="{{ route('seek', ['topic' => $topic->slug]) }}" class="ix-btn ix-btn--primary">
                <span class="material-symbols-rounded" aria-hidden="true">add_comment</span>Ask about this
            </a>
        @endif
    </div>

    <div class="ix-state-bar">
        <div class="ix-readouts" aria-label="Public counts for this topic">
            <div class="ix-readout" style="border-left: 0;"><x-ix.figure :value="$counts['questions']" /><span class="block text-xs" style="color: var(--ix-text-3);">Public questions</span></div>
            <div class="ix-readout"><x-ix.figure :value="$counts['answered']" /><span class="block text-xs" style="color: var(--ix-text-3);">Answered</span></div>
            <div class="ix-readout"><x-ix.figure :value="$counts['unanswered']" /><span class="block text-xs" style="color: var(--ix-text-3);">Unanswered</span></div>
            <div class="ix-readout"><x-ix.figure :value="$counts['solutions']" /><span class="block text-xs" style="color: var(--ix-text-3);">Public solutions</span></div>
        </div>
    </div>
    @if ($questionTotal > $counts['questions'] || $solutionTotal > $counts['solutions'])
        <p class="ix-hint ix-topichint">The lists below can also include work that is not public but that you are allowed to see, such as your company's own questions. The counts above never do.</p>
    @endif

    <section class="mb-8" aria-labelledby="topic-questions">
        <h2 id="topic-questions" class="ix-topich2">Questions</h2>
        @if ($questions->isEmpty())
            <div class="ix-panel ix-panel__body" style="color: var(--ix-text-2);">Nothing has been asked under this topic yet.@if ($topic->active) <a href="{{ route('seek', ['topic' => $topic->slug]) }}" style="color: var(--ix-text); text-decoration: underline;">Ask the first question</a>@endif</div>
        @else
            <ul class="ix-qlist">
                @foreach ($questions as $question)
                    @php($isSolved = (int) $question->status === 1)
                    @php($replies = $question->comments_count + $question->answers_count)
                    <li class="ix-q {{ $isSolved ? 'is-solved' : '' }}">
                        <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-q__count" aria-label="{{ $replies }} {{ Str::plural('reply', $replies) }}">
                            <span class="ix-stencil-num">{{ str_pad((string) min($replies, 99), 2, '0', STR_PAD_LEFT) }}</span>
                            <small>{{ Str::plural('reply', $replies) }}</small>
                        </a>
                        <div class="ix-q__body">
                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-q__title">{{ $question->question }}</a>
                            <p class="ix-q__meta">
                                <span class="ix-ref">{{ \App\Support\Reference::for($question) }}</span>
                                <span>{{ $question->user->name ?? 'Former member' }}</span>
                                <span>{{ $question->created_at->diffForHumans() }}</span>
                                <span>{{ $question->isHidden() ? 'You and moderators' : ($question->isPublic() ? 'Everyone' : 'Your company') }}</span>
                            </p>
                            @include('topics._chips', ['item' => $question])
                        </div>
                        <span class="ix-q__state {{ $isSolved ? 'is-done' : 'is-open' }}">{{ $isSolved ? 'Solved' : 'Waiting' }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($questionTotal > $questions->count())<p class="ix-hint">Showing the latest {{ $questions->count() }} of {{ $questionTotal }}. <a href="{{ route('questions', ['topic' => $topic->slug]) }}">See them all in Questions</a>.</p>@endif
        @endif
    </section>

    <section aria-labelledby="topic-solutions">
        <h2 id="topic-solutions" class="ix-topich2">Solutions</h2>
        @if ($solutions->isEmpty())
            <div class="ix-panel ix-panel__body" style="color: var(--ix-text-2);">No solution has been written under this topic yet.@if ($topic->active) <a href="{{ route('add', ['topic' => $topic->slug]) }}" style="color: var(--ix-text); text-decoration: underline;">Write one</a>@endif</div>
        @else
            <ul class="ix-library">
                @foreach ($solutions as $solution)
                    <li class="ix-sol">
                        <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-sol__link">
                            <span class="ix-sol__steps">
                                <span class="ix-stencil-num">{{ str_pad((string) min((int) $solution->steps, 99), 2, '0', STR_PAD_LEFT) }}</span>
                                <small>{{ Str::plural('step', (int) $solution->steps) }}</small>
                            </span>
                            <span class="ix-sol__text">
                                <span class="ix-sol__title">{{ $solution->solution_title }}</span>
                                <span class="ix-sol__ex">{{ Str::limit($solution->solution_description, 150) }}</span>
                            </span>
                        </a>
                        <p class="ix-sol__meta">
                            <span class="ix-ref">{{ \App\Support\Reference::for($solution) }}</span>
                            @if ($solution->isVerified())<span class="ix-chip ix-chip--done">Verified by moderator</span>@endif
                            <span>{{ $solution->user->name ?? 'Former member' }}</span>
                            <span>{{ $solution->isHidden() ? 'You and moderators' : ($solution->isPublic() ? 'Everyone' : 'Your company') }}</span>
                        </p>
                        @include('topics._chips', ['item' => $solution])
                    </li>
                @endforeach
            </ul>
            @if ($solutionTotal > $solutions->count())<p class="ix-hint">Showing the latest {{ $solutions->count() }} of {{ $solutionTotal }}. <a href="{{ route('solutions', ['topic' => $topic->slug]) }}">See them all in Solutions</a>.</p>@endif
        @endif
    </section>
</x-app-layout>
