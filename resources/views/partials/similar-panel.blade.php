{{--
    R-16 duplicate detection panel. Include INSIDE the composer form with:
      $kind    'question' or 'solution'
      $fields  the form field names whose text to check, e.g. ['question', 'description']
    Links open in a new tab so the draft is never lost. Never blocks publishing.
--}}
<div x-data="similarFinder({ url: @js(route('knowledge.similar')), kind: @js($kind), fields: @js($fields), debounce: {{ (int) config('search.similar_debounce_ms') }} })" x-show="results.length" x-cloak
     class="ix-similar" role="region" aria-label="Existing content that may help">
    <p class="ix-similar__head"><span class="material-symbols-rounded" aria-hidden="true">travel_explore</span> We found some existing content that may help you</p>
    <p class="ix-hint" style="margin:0">Open anything in a new tab — your draft stays here. If yours genuinely adds something different, just keep going.</p>
    <ul class="ix-similar__list">
        <template x-for="r in results" :key="r.kind + r.id">
            <li class="ix-similar__item" :class="{ 'is-strong': r.label === 'very-similar' }">
                <span class="ix-similar__badge" x-text="r.label === 'very-similar' ? 'Very similar' : 'Related'"></span>
                <a :href="r.url" target="_blank" rel="noopener">
                    <span x-text="(r.kind === 'question' ? 'Question: ' : 'Solution: ') + r.title"></span>
                </a>
                <small>
                    <span x-text="r.excerpt"></span>
                    <span x-text="' — ' + r.author + ', ' + r.date + (r.tried ? ', tried by ' + r.tried : '')"></span>
                </small>
            </li>
        </template>
    </ul>
</div>
