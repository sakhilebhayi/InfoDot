@php
    $texts = array_map(fn ($s) => $s, $this->industry['searches']);
    $team = auth()->user()?->currentTeam;
@endphp
<div class="ix-search"
     x-data="{ open: false, ph: 0, texts: @js($texts), timer: null }"
     x-init="if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) { timer = setInterval(() => { if (! open && document.activeElement !== $refs.q) ph = (ph + 1) % texts.length }, 4500) }"
     @click.outside="open = false"
     @keydown.window.prevent.ctrl.k="$refs.q.focus()"
     @keydown.window.prevent.meta.k="$refs.q.focus()"
     @keydown.escape="open = false; $refs.q.blur()">
    <label for="ix-search-input" class="ix-sr">Search questions and solutions, jump to a reference, or start a new question</label>
    <form action="{{ route('solution_search_results') }}" method="GET" role="search">
        <input
            id="ix-search-input"
            name="search"
            x-ref="q"
            type="search"
            placeholder="Try: {{ $texts[0] }}"
            :placeholder="'Try: ' + texts[ph]"
            autocomplete="off"
            class="ix-input"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="ix-search-list"
            :aria-expanded="open"
            @focus="open = true"
            wire:model.live.debounce.200ms="query"
            wire:keydown.escape="$set('query', '')"
            wire:keydown.arrow-up.prevent="decrementHighlight"
            wire:keydown.arrow-down.prevent="incrementHighlight"
            wire:keydown.enter="openHighlighted"
            @keydown.enter="if ($wire.query.trim() !== '') $event.preventDefault()"
        />
        <kbd class="ix-kbd" aria-hidden="true" x-show="!open">Ctrl K</kbd>

        <button type="submit" class="ix-search__go" aria-label="Search questions and solutions">
            <span class="material-symbols-rounded" aria-hidden="true">search</span>
        </button>
    </form>

    <div id="ix-search-list" class="ix-results" x-show="open" x-cloak x-transition.opacity.duration.100ms role="listbox" aria-label="Suggestions">
        @if(blank(trim($query)))
            <div class="ix-quick">
                <div class="ix-pop__head">Try asking <span class="ix-results__tag">{{ $this->industry['label'] }}</span></div>
                @foreach($this->starters as $starter)
                    <a class="ix-results__item" href="{{ route('seek', ['question' => $starter]) }}" role="option">
                        <span class="material-symbols-rounded" aria-hidden="true">lightbulb</span>{{ $starter }}
                    </a>
                @endforeach

                @if(count($this->recent))
                    <div class="ix-pop__head">New in {{ $team?->name ?? 'your workspace' }}</div>
                    @foreach($this->recent as $item)
                        <a class="ix-results__item" href="{{ $item['url'] }}" role="option">
                            <span class="ix-ref">{{ $item['ref'] }}</span>{{ $item['label'] }}
                        </a>
                    @endforeach
                @endif

                <div class="ix-pop__head">Go to</div>
                <div class="ix-quick__row">
                    <a class="ix-quick__chip" href="{{ route('seek') }}"><span class="material-symbols-rounded" aria-hidden="true">add_comment</span>Ask a question</a>
                    <a class="ix-quick__chip" href="{{ route('add') }}"><span class="material-symbols-rounded" aria-hidden="true">checklist</span>Add a solution</a>
                    <a class="ix-quick__chip" href="{{ route('people') }}"><span class="material-symbols-rounded" aria-hidden="true">groups</span>People</a>
                    <button type="button" class="ix-quick__chip" wire:click="openPlatform('files')"><span class="material-symbols-rounded" aria-hidden="true">folder</span>Files</button>
                    <button type="button" class="ix-quick__chip" wire:click="openPlatform('docs')"><span class="material-symbols-rounded" aria-hidden="true">description</span>Documents</button>
                </div>
                <p class="ix-results__foot">Type a reference such as <span class="ix-ref">{{ $team?->code ?? 'INFO' }}-Q-000001</span> to open that item.</p>
            </div>
        @else
            @if(! $this->reference && $this->solutions->isEmpty() && $this->questions->isEmpty())
                <p class="ix-results__foot">Nothing matches &ldquo;{{ $query }}&rdquo;. Try fewer words, or ask it as a question.</p>
            @endif
            @php($lastKind = null)
            @foreach($this->items as $i => $item)
                @if($item['kind'] !== $lastKind)
                    @php($lastKind = $item['kind'])
                    @if(in_array($item['kind'], ['solution', 'question']))
                        <div class="ix-pop__head">{{ $item['kind'] === 'solution' ? 'Solutions' : 'Questions' }}</div>
                    @elseif($item['kind'] === 'ask')
                        <div class="ix-pop__head">Not here?</div>
                    @endif
                @endif
                <a href="{{ $item['url'] }}" class="ix-results__item {{ $highlightIndex === $i ? 'is-active' : '' }} {{ in_array($item['kind'], ['ask', 'all']) ? 'ix-results__item--action' : '' }}" role="option" aria-selected="{{ $highlightIndex === $i ? 'true' : 'false' }}">
                    @if(isset($item['ref']))<span class="ix-ref">{{ $item['ref'] }}</span>
                    @else<span class="material-symbols-rounded" aria-hidden="true">{{ $item['icon'] }}</span>@endif
                    <span class="ix-results__label">@if(in_array($item['kind'], ['solution', 'question'])){{ $this->mark($item['label']) }}@else{{ $item['label'] }}@endif</span>
                </a>
            @endforeach
        @endif
    </div>
</div>
