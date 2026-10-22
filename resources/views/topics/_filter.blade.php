{{-- Narrow a list to one topic (or to what has none yet). A plain GET form: it works without scripting and the result can be linked to. --}}
@php($isNone = $filterSlug === 'none')
<form class="ix-topicbar" action="{{ $route }}" method="GET" role="search" aria-label="Filter by topic">
    <label for="topic-filter">Topic</label>
    <select id="topic-filter" name="topic" class="ix-input ix-select">
        <option value="">All topics</option>
        @foreach ($topicChoices as $choice)
            <option value="{{ $choice->slug }}" @selected($filterSlug === $choice->slug)>{{ $choice->name }}</option>
        @endforeach
        @if ($activeTopic && ! $activeTopic->active)
            <option value="{{ $activeTopic->slug }}" selected>{{ $activeTopic->name }} (retired)</option>
        @endif
        <option value="none" @selected($isNone)>Untagged</option>
    </select>
    <button type="submit" class="ix-btn">Show</button>
    @if ($filterSlug !== '')<a class="ix-topicbar__clear" href="{{ $route }}">Clear</a>@endif
    <a class="ix-topicbar__all" href="{{ route('topics') }}">Browse all topics</a>
</form>
