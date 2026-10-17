<div class="ix-search" @click.outside="$wire.set('query', '')">
    <span class="material-symbols-rounded" aria-hidden="true">search</span>
    <label for="ix-search-input" class="ix-sr">Search questions and solutions</label>
    <input
        id="ix-search-input"
        type="search"
        placeholder="Search questions and solutions"
        autocomplete="off"
        class="ix-input"
        wire:model.live.debounce.250ms="query"
        wire:keydown.escape="$set('query', '')"
        wire:keydown.arrow-up.prevent="decrementHighlight"
        wire:keydown.arrow-down.prevent="incrementHighlight"
    />

    @if(!empty($query))
        <div class="ix-results" role="listbox">
            @php $i = 0; @endphp
            @forelse($this->solutions as $solution)
                <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-results__item {{ $highlightIndex === $i++ ? 'is-active' : '' }}" role="option">
                    <span class="material-symbols-rounded" aria-hidden="true">checklist</span>{{ $solution->solution_title }}
                </a>
            @empty
            @endforelse
            @foreach($this->questions as $question)
                <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-results__item {{ $highlightIndex === $i++ ? 'is-active' : '' }}" role="option">
                    <span class="material-symbols-rounded" aria-hidden="true">help</span>{{ $question->question }}
                </a>
            @endforeach

            @if($this->solutions->isEmpty() && $this->questions->isEmpty())
                <p class="ix-results__foot">Nothing matches &ldquo;{{ $query }}&rdquo;. Try fewer words, or ask it as a question.</p>
            @endif
        </div>
    @endif
</div>
