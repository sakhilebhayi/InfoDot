{{--
    A searchable select (R-64 phase 4). The native <select> underneath carries the value and submits with the form, and is what people see when scripts
    have not loaded; the search box, list and chips are drawn over it by controls.js. Options come as `options` (value => label) or as <option> elements
    in the slot. `multiple` makes it a multi-select with removable chips (submitted as name[]). A single select has an empty first choice unless it is
    `required`, and a stored value that is not in the list is added to it, so saving never changes what was stored. Extra attributes (wire:model, data-*)
    go to the select. The browser's "required" prompt cannot appear on a hidden control, so the server's validation is what enforces it. Use inside form.field.
--}}
@props(['name', 'id' => null, 'options' => null, 'value' => null, 'multiple' => false, 'required' => false, 'placeholder' => 'Search', 'empty' => 'No matches'])
@php
    $id = $id ?? $name;
    $chosen = collect((array) old($name, $value))->map(fn ($v) => (string) $v)->filter(fn ($v) => $v !== '')->values()->all();
    $known = $options === null ? [] : collect($options)->keys()->map(fn ($k) => (string) $k)->all();
    $extra = $options === null ? [] : array_values(array_diff($chosen, $known));
    $invalid = $errors->has($name) || $errors->has($name.'.*');
@endphp
{{-- wire:ignore: Livewire leaves the drawn controls alone when it re-renders around them. --}}
<div class="ix-combo" wire:ignore x-data="ixCombobox({ multiple: @js((bool) $multiple), empty: @js($empty) })" x-bind:class="{ 'is-ready': ready }"
     x-on:keydown.escape="open && ($event.stopPropagation(), hide())" x-on:click.outside="hide()" x-on:focusout="if (open && $event.relatedTarget && !$el.contains($event.relatedTarget)) hide()">
    <select {{ $attributes->merge(['class' => 'ix-select']) }} x-ref="native" name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $id }}" @if ($multiple) multiple @endif @if ($required) required @endif
        aria-describedby="{{ $id }}-msg" @if ($invalid) aria-invalid="true" @endif>
        @if ($options !== null)
            @if (! $multiple && ! $required && ! in_array('', $known, true))<option value="">&nbsp;</option>@endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $chosen, true))>{{ $optionLabel }}</option>
            @endforeach
            @foreach ($extra as $stored)
                <option value="{{ $stored }}" selected>{{ $stored }}</option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>

    <div class="ix-combo__ui" x-show="ready" x-cloak>
        <div class="ix-combo__box" x-ref="box" x-bind:class="{ 'is-open': open }" @if ($invalid) data-invalid @endif>
            <template x-for="chip in (multiple ? chips : [])" x-bind:key="chip.value">
                <span class="ix-combo__chip"><span x-text="chip.label"></span><button type="button" tabindex="-1" x-on:click="drop(chip)" x-bind:aria-label="'Remove ' + chip.label"><span aria-hidden="true">&times;</span></button></span>
            </template>
            <input type="text" x-ref="input" class="ix-combo__input" role="combobox" autocomplete="off" spellcheck="false"
                aria-autocomplete="list" aria-haspopup="listbox" x-bind:aria-expanded="open ? 'true' : 'false'" x-bind:aria-controls="uid + '-list'"
                x-bind:aria-activedescendant="open && active >= 0 ? optionId(active) : null" aria-describedby="{{ $id }}-msg" @if ($invalid) aria-invalid="true" @endif
                x-bind:value="text" x-bind:placeholder="multiple && chips.length ? '' : @js($placeholder)"
                x-on:input.stop="typing($event)" x-on:focus="$event.target.select()" x-on:click="show()"
                x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.home="open && ($event.preventDefault(), jump(false))"
                x-on:keydown.end="open && ($event.preventDefault(), jump(true))" x-on:keydown.enter="enter($event)" x-on:keydown.tab="hide()" x-on:keydown.backspace="backspace()">
            <button type="button" class="ix-combo__clear" tabindex="-1" x-show="hasBlank && !multiple && chosen.length" x-on:click="clear()" aria-label="Clear the choice"><span aria-hidden="true">&times;</span></button>
            <button type="button" class="ix-combo__toggle" tabindex="-1" x-on:click="toggle()" aria-label="Show the options"></button>
        </div>
        <ul class="ix-combo__list" x-ref="list" role="listbox" x-bind:id="uid + '-list'" x-bind:aria-multiselectable="multiple ? 'true' : null" x-show="open" x-cloak x-transition:enter="ix-pop-in" x-transition:enter-start="ix-pop-in--from" x-transition:enter-end="ix-pop-in--to">
            <template x-for="(option, index) in shown" x-bind:key="option.value">
                <li role="option" x-bind:id="optionId(index)" x-bind:aria-selected="isChosen(option) ? 'true' : 'false'" x-bind:aria-disabled="option.disabled ? 'true' : null"
                    x-bind:class="{ 'is-active': index === active, 'is-chosen': isChosen(option), 'is-disabled': option.disabled }"
                    x-on:mousedown.prevent x-on:click="option.disabled || pick(option)" x-on:mousemove="active = index">
                    <span x-text="option.label"></span>
                </li>
            </template>
            <li class="ix-combo__empty" x-show="shown.length === 0" x-text="@js($empty)"></li>
        </ul>
        <span class="ix-sr" role="status" aria-live="polite" x-text="status"></span>
    </div>
</div>
