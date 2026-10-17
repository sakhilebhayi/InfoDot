<div class="ix-search"
     x-data="{ open: false }"
     @click.outside="open = false"
     @keydown.window.prevent.ctrl.k="$refs.q.focus()"
     @keydown.window.prevent.meta.k="$refs.q.focus()"
     @keydown.escape="open = false; $refs.q.blur()">
    <span class="material-symbols-rounded" aria-hidden="true">search</span>
    <label for="ix-search-input" class="ix-sr">Search knowledge, jump to a reference, or run a command</label>
    <input
        id="ix-search-input"
        x-ref="q"
        type="search"
        placeholder="Search or reference"
        autocomplete="off"
        class="ix-input"
        @focus="open = true"
        wire:model.live.debounce.250ms="query"
        wire:keydown.escape="$set('query', '')"
        wire:keydown.arrow-up.prevent="decrementHighlight"
        wire:keydown.arrow-down.prevent="incrementHighlight"
    />
    <kbd class="ix-kbd" aria-hidden="true" x-show="!open">Ctrl K</kbd>

    <div class="ix-results" x-show="open" x-cloak x-transition.opacity.duration.100ms>
        @if(blank($query))
            <div class="ix-quick">
                <div class="ix-pop__head">Go to</div>
                <a class="ix-pop__item" href="{{ route('seek') }}"><span class="material-symbols-rounded" aria-hidden="true">add</span>Ask a question</a>
                <a class="ix-pop__item" href="{{ route('add') }}"><span class="material-symbols-rounded" aria-hidden="true">add</span>Add a solution</a>
                <a class="ix-pop__item" href="{{ route('people') }}"><span class="material-symbols-rounded" aria-hidden="true">groups</span>People</a>
                <button type="button" class="ix-pop__item" wire:click="openPlatform('files')"><span class="material-symbols-rounded" aria-hidden="true">folder</span>Open Files<span class="material-symbols-rounded" aria-hidden="true" style="margin-left:auto;font-size:16px">arrow_outward</span></button>
                <button type="button" class="ix-pop__item" wire:click="openPlatform('docs')"><span class="material-symbols-rounded" aria-hidden="true">description</span>Open Documents<span class="material-symbols-rounded" aria-hidden="true" style="margin-left:auto;font-size:16px">arrow_outward</span></button>
                <p class="ix-results__foot">Type a reference such as <span class="ix-ref">{{ auth()->user()->currentTeam?->code ?? 'INFO' }}-Q-000001</span> to open that item.</p>
            </div>
        @else
            @php $i = 0; @endphp
            @if($this->reference)
                <a href="{{ \App\Support\Reference::route($this->reference) }}" class="ix-results__item is-active" role="option">
                    <span class="ix-ref">{{ \App\Support\Reference::for($this->reference) }}</span>{{ $this->reference->question ?? $this->reference->solution_title }}
                </a>
            @endif
            @foreach($this->solutions as $solution)
                <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-results__item {{ $highlightIndex === $i++ ? 'is-active' : '' }}" role="option">
                    <span class="material-symbols-rounded" aria-hidden="true">checklist</span>{{ $solution->solution_title }}
                </a>
            @endforeach
            @foreach($this->questions as $question)
                <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-results__item {{ $highlightIndex === $i++ ? 'is-active' : '' }}" role="option">
                    <span class="material-symbols-rounded" aria-hidden="true">forum</span>{{ $question->question }}
                </a>
            @endforeach

            @if(! $this->reference && $this->solutions->isEmpty() && $this->questions->isEmpty())
                <p class="ix-results__foot">Nothing matches &ldquo;{{ $query }}&rdquo;. Try fewer words, or ask it as a question.</p>
            @endif
        @endif
    </div>
</div>
