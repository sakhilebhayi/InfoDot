{{-- Search results can be narrowed to one topic. The words stay in the box; with a topic and no words, the whole topic is listed. --}}
<form class="ix-topicbar" action="{{ route('solution_search_results') }}" method="GET" role="search" aria-label="Search by words and topic">
    <label for="results-search">Words</label>
    <input id="results-search" type="search" name="search" value="{{ $searchTerm }}" class="ix-input" autocomplete="off">
    <label for="results-topic">Topic</label>
    <select id="results-topic" name="topic" class="ix-input ix-select">
        <option value="">Any topic</option>
        @foreach ($topicChoices as $choice)
            <option value="{{ $choice->slug }}" @selected($topicSlug === $choice->slug)>{{ $choice->name }}</option>
        @endforeach
        @if ($activeTopic && ! $activeTopic->active)
            <option value="{{ $activeTopic->slug }}" selected>{{ $activeTopic->name }} (retired)</option>
        @endif
    </select>
    <button type="submit" class="ix-btn ix-btn--arrow" aria-label="Search"><x-ix.arrow /></button>
    @if ($topicSlug !== '')<a class="ix-topicbar__clear" href="{{ route('solution_search_results', ['search' => $searchTerm]) }}">Any topic</a>@endif
</form>
@if ($topicSlug !== '' && ! $activeTopic)
    <p class="ix-hint" role="status">There is no topic with that address, so nothing matches.</p>
@endif
