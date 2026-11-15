@php
    $texts = array_map(fn ($s) => $s, $this->industry['searches']);
    $team = auth()->user()?->currentTeam;

    // The attributes InfoDot's search input has always had (Alpine's placeholder rotation, Livewire's model with the configured
    // debounce, the arrow-key and Enter handlers). A component tag cannot take an attribute whose NAME holds a Blade echo
    // (wire:model.live.debounce.{{ n }}ms), so they travel as one bag.
    $attributes = new \Illuminate\View\ComponentAttributeBag([
        'x-ref' => 'q',
        'x-bind:placeholder' => "'Try: ' + texts[ph]",
        'wire:model.live.debounce.'.(int) config('search.debounce_ms').'ms' => 'query',
        'wire:keydown.arrow-up.prevent' => 'decrementHighlight',
        'wire:keydown.arrow-down.prevent' => 'incrementHighlight',
        'wire:keydown.enter' => 'openHighlighted',
        'x-on:keydown.enter' => "if (\$wire.query.trim() !== '') \$event.preventDefault()",
    ]);
@endphp
{{-- The header search. Its behaviour is unchanged (R-52): the same Livewire component, query, debounce, states, keyboard model and
     results; only the pill and the dropdown are drawn by the shared dot-ui search (x-dot.search, classes dsr-*), which was copied
     from this bar, so the same box can be centred by the header's grid and open as a sheet on a phone. The rotating "Try:" placeholder
     stays Alpine, the Ctrl K shortcut and the open and close are dot-search.js. --}}
<div x-data="{ ph: 0, texts: @js($texts), timer: null }"
     x-init="if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) { timer = setInterval(() => { if (! $root.querySelector('.dsr').classList.contains('is-open') && document.activeElement !== $refs.q) ph = (ph + 1) % texts.length }, 4500) }">
    <x-dot.search
        id="ix-search-input"
        name="search"
        :action="route('solution_search_results')"
        label="Search questions and solutions, jump to a reference, or start a new question"
        placeholder="Try: {{ $texts[0] }}"
        hint="Ctrl K"
        :escape-clears="true"
        {{ $attributes }}
        :root-attributes="['wire:ignore.self' => '']">
        <x-slot:results>
            @if(blank(trim($query)))
                <div class="dsr-quick">
                    <div class="dsr-head">Try asking <span class="dsr-tag">{{ $this->industry['label'] }}</span></div>
                    @foreach($this->starters as $starter)
                        <a class="dsr-item" href="{{ route('seek', ['question' => $starter]) }}" role="option">
                            <span class="material-symbols-rounded dsr-glyph" aria-hidden="true">lightbulb</span>{{ $starter }}
                        </a>
                    @endforeach

                    @if(count($this->recent))
                        <div class="dsr-head">New in {{ $team?->name ?? 'your workspace' }}</div>
                        @foreach($this->recent as $item)
                            <a class="dsr-item" href="{{ $item['url'] }}" role="option">
                                <span class="dsr-ref">{{ $item['ref'] }}</span>{{ $item['label'] }}
                            </a>
                        @endforeach
                    @endif

                    <div class="dsr-head">Go to</div>
                    <div class="dsr-chips">
                        <a class="dsr-chip" role="option" href="{{ route('seek') }}"><span class="material-symbols-rounded dsr-glyph" aria-hidden="true">add_comment</span>Ask a question</a>
                        <a class="dsr-chip" role="option" href="{{ route('add') }}"><span class="material-symbols-rounded dsr-glyph" aria-hidden="true">checklist</span>Add a solution</a>
                        {{-- People, Files and Documents belong to a signed-in person's company: a visitor is not offered them (the two buttons did nothing when a visitor pressed them). --}}
                        @auth
                            <a class="dsr-chip" role="option" href="{{ route('people') }}"><span class="material-symbols-rounded dsr-glyph" aria-hidden="true">groups</span>People</a>
                            <button type="button" class="dsr-chip" role="option" wire:click="openPlatform('files')"><span class="material-symbols-rounded dsr-glyph" aria-hidden="true">folder</span>Files</button>
                            <button type="button" class="dsr-chip" role="option" wire:click="openPlatform('docs')"><span class="material-symbols-rounded dsr-glyph" aria-hidden="true">description</span>Documents</button>
                        @endauth
                    </div>
                    <p class="dsr-foot">Type a reference such as <span class="dsr-ref">{{ $team?->code ?? 'INFO' }}-Q-000001</span> to open that item.</p>
                </div>
            @else
                @if(! $this->reference && $this->solutions->isEmpty() && $this->questions->isEmpty())
                    <p class="dsr-foot">Nothing matches &ldquo;{{ $query }}&rdquo;. Try fewer words, or ask it as a question.</p>
                @endif
                {{-- Files and Documents answer a moment later; the key restarts the load for each new query. --}}
                @if(mb_strlen(trim($query)) >= 3 && $externalFor !== trim($query))
                    <div wire:key="ext-{{ trim($query) }}" wire:init="loadExternal" class="dsr-foot" aria-live="polite">Looking in Files and Documents&hellip;</div>
                @endif
                @php($lastKind = null)
                @foreach($this->items as $i => $item)
                    @if($item['kind'] !== $lastKind)
                        @php($lastKind = $item['kind'])
                        @if(in_array($item['kind'], ['solution', 'question']))
                            <div class="dsr-head">{{ $item['kind'] === 'solution' ? 'Solutions' : 'Questions' }}</div>
                        @elseif($item['kind'] === 'external')
                            <div class="dsr-head">In Files and Documents</div>
                        @elseif($item['kind'] === 'ask')
                            <div class="dsr-head">Not here?</div>
                        @endif
                    @endif
                    <a href="{{ $item['url'] }}" class="dsr-item {{ $highlightIndex === $i ? 'is-active' : '' }} {{ in_array($item['kind'], ['ask', 'all']) ? 'dsr-item--action' : '' }}" role="option" aria-selected="{{ $highlightIndex === $i ? 'true' : 'false' }}">
                        @if(isset($item['ref']))<span class="dsr-ref">{{ $item['ref'] }}</span>
                        @else<span class="material-symbols-rounded dsr-glyph" aria-hidden="true">{{ $item['icon'] }}</span>@endif
                        <span class="dsr-label">@if(in_array($item['kind'], ['solution', 'question', 'external'])){{ $this->mark($item['label']) }}@else{{ $item['label'] }}@endif</span>
                        @if($item['kind'] === 'external')<span class="dsr-tag">{{ $item['platform'] }}</span>@endif
                    </a>
                @endforeach
            @endif
        </x-slot:results>
    </x-dot.search>
</div>
