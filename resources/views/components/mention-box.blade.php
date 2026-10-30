{{--
    R-16 mention wrapper: put the textarea in the slot. Typing @name opens the picker (events bubble up to this
    wrapper); chosen people/businesses become chips and a JSON payload. $payloadModel for Livewire forms, $name for
    plain forms; $clearEvent empties the chips after a Livewire submit.
--}}
@props(['payloadModel' => null, 'name' => 'mentions', 'clearEvent' => null])
<div class="ix-mention" x-data="mentionPicker({ url: @js(route('mentions.search')), max: {{ \App\Services\Knowledge\MentionService::MAX_PER_ITEM }} })"
     @input="onInput($event)" @keydown="onKey($event)" @click.outside="open = false"
     @if ($clearEvent) x-on:{{ $clearEvent }}.window="clearAll()" @endif>
    {{ $slot }}

    <div class="ix-mention__drop" x-show="open" x-cloak role="listbox" aria-label="People and businesses to mention">
        <template x-for="(r, i) in results" :key="r.type + r.id">
            <button type="button" class="ix-mention__option" :class="{ 'is-active': i === highlight }" role="option" :aria-selected="i === highlight"
                    @mousedown.prevent="choose(r)">
                <span x-text="r.name"></span>
                <small x-text="r.detail"></small>
            </button>
        </template>
    </div>

    <p class="ix-hint ix-mention__hint">Type @ and a name to bring someone with the right experience into this.</p>
    <p class="ix-error" x-show="note" x-text="note" x-cloak role="alert"></p>

    <ul class="ix-mention__chips" x-show="items.length" x-cloak aria-label="Will be notified">
        <template x-for="it in items" :key="it.type + it.id">
            <li class="ix-chip ix-chip--info">
                <span x-text="'@' + it.name"></span>
                <button type="button" class="ix-mention__x" @click="remove(it)" :aria-label="'Do not notify ' + it.name">&times;</button>
            </li>
        </template>
    </ul>

    @if ($payloadModel)
        <input type="hidden" x-ref="payload" wire:model="{{ $payloadModel }}">
    @else
        <input type="hidden" x-ref="payload" name="{{ $name }}">
    @endif
</div>
