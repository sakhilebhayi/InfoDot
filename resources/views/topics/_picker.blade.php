{{--
    Choose 1 to 3 topics. `choices` are the topics on offer, `selected` the ids already chosen, `idPrefix` keeps the ids
    unique when a page has more than one picker. With `live` the choice is bound to a Livewire property instead.
--}}
@php
    $idPrefix ??= 'topic';
    $selected = array_map('intval', $selected ?? []);
    $live ??= null;
    $legend ??= 'Topics';
@endphp
<fieldset class="ix-topicpick" @unless ($live) x-data="{ picked: @js($selected), max: {{ \App\Models\Topic::MAX_PER_ITEM }} }" @endunless>
    <legend class="ix-label">{{ $legend }}</legend>
    <p class="ix-hint" id="{{ $idPrefix }}-hint">Choose 1 to {{ \App\Models\Topic::MAX_PER_ITEM }}. People who know the subject look here first.
        @unless ($live)<span class="ix-topicpick__count" aria-live="polite" x-text="picked.length + ' of ' + max + ' chosen'">{{ count($selected) }} of {{ \App\Models\Topic::MAX_PER_ITEM }} chosen</span>@endunless
    </p>
    <div class="ix-topicpick__grid" aria-describedby="{{ $idPrefix }}-hint">
        @foreach ($choices as $choice)
            <label class="ix-topicpick__opt" for="{{ $idPrefix }}-{{ $choice->id }}" @unless ($choice->active) title="Switched off: it can be kept but not newly chosen" @endunless>
                @if ($live)
                    <input type="checkbox" id="{{ $idPrefix }}-{{ $choice->id }}" name="topics[]" value="{{ $choice->id }}" wire:model="{{ $live }}">
                @else
                    <input type="checkbox" id="{{ $idPrefix }}-{{ $choice->id }}" name="topics[]" value="{{ $choice->id }}" x-model.number="picked"
                           :disabled="picked.length >= max && ! picked.includes({{ $choice->id }})">
                @endif
                <span>{{ $choice->name }}@unless ($choice->active) <small>(retired)</small>@endunless</span>
            </label>
        @endforeach
    </div>
</fieldset>
