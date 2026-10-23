<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Verified library</h1>
            <p class="ix-lede">Answers and solutions that members of the community vouched for and a moderator then adopted. What you read is the live page: if it is changed or hidden, the library follows.</p>
        </div>
    </div>

    <form class="ix-topicbar" action="{{ route('library') }}" method="GET" role="search" aria-label="Filter the library by topic">
        <label for="library-topic">Topic</label>
        <select id="library-topic" name="topic" class="ix-input ix-select">
            <option value="">All topics</option>
            @foreach ($topicChoices as $choice)
                <option value="{{ $choice->slug }}" @selected($topic?->slug === $choice->slug)>{{ $choice->name }}</option>
            @endforeach
            @if ($topic && ! $topic->active)
                <option value="{{ $topic->slug }}" selected>{{ $topic->name }} (retired)</option>
            @endif
        </select>
        <button type="submit" class="ix-btn">Show</button>
        @if ($topic)<a class="ix-topicbar__clear" href="{{ route('library') }}">Clear</a>@endif
        <a class="ix-topicbar__all" href="{{ route('topics') }}">Browse all topics</a>
    </form>

    @if ($entries->isEmpty())
        <section class="ix-empty">
            @if ($topic)
                <h2>Nothing in the library under {{ $topic->name }} yet</h2>
                <p>Try another topic, or <a href="{{ route('library') }}" style="text-decoration: underline;">see everything in the library</a>.</p>
            @else
                <h2>The library is empty so far</h2>
                <p>A moderator adds an answer or a solution once other members have accepted or verified it. Until then, <a href="{{ route('questions') }}" style="text-decoration: underline;">the questions</a> and <a href="{{ route('solutions') }}" style="text-decoration: underline;">solutions</a> are the place to look.</p>
            @endif
        </section>
    @else
        <ul class="ix-lib">
            @foreach ($entries as $entry)
                @php($isAnswer = $entry->kind === 'answer')
                @php($source = $isAnswer ? $entry->answer : $entry->solution)
                @php($home = $isAnswer ? $source->question : $source)
                <li class="ix-lib__item">
                    <span class="ix-lib__kind" aria-hidden="true">
                        <span class="ix-stencil-num">{{ $isAnswer ? 'A' : 'S' }}</span>
                        <small>{{ $isAnswer ? 'Answer' : 'Solution' }}</small>
                    </span>
                    <div class="ix-lib__body">
                        <a class="ix-lib__title" href="{{ $isAnswer ? route('questions.view', ['qid' => $source->question_id]).'#answer-'.$source->id : route('solutions.view', ['id' => $source->id]) }}">
                            <span class="ix-sr">{{ $isAnswer ? 'Answer to: ' : 'Solution: ' }}</span>{{ $isAnswer ? $home->question : $source->solution_title }}
                        </a>
                        <div class="ix-lib__meta">
                            <span class="ix-chip ix-chip--done">Verified by moderators</span>
                            @include('topics._chips', ['item' => $home])
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $entries->links() }}
    @endif
</x-app-layout>
