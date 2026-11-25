{{--
    A date field with a calendar (R-64 phase 4). It is a real <input type="date"> that controls.js turns into a text box taking yyyy-mm-dd, with a
    calendar beside it, so the value that is submitted is always an ISO date and the field still works as a native date input without scripts.
    `min`, `max` (yyyy-mm-dd) and `disabled-days` (list of yyyy-mm-dd) mark the days that cannot be chosen. Use inside form.field.
--}}
@props(['name', 'id' => null, 'value' => null, 'min' => null, 'max' => null, 'disabledDays' => [], 'weekStart' => 1])
@php($id = $id ?? $name)
<div class="ix-date" wire:ignore x-bind:class="{ 'is-ready': ready }" x-data="ixDatePicker({ min: @js((string) $min), max: @js((string) $max), disabled: @js(array_values($disabledDays)), weekStart: {{ (int) $weekStart }} })"
     x-on:click.outside="hide(false)">
    <input {{ $attributes->merge(['type' => 'date', 'name' => $name, 'id' => $id, 'class' => 'ix-input']) }} x-ref="input" value="{{ old($name, $value) }}"
        @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif
        aria-describedby="{{ $id }}-msg" @if ($errors->has($name)) aria-invalid="true" @endif
        x-on:input="typed()" x-on:blur="check()" x-on:keydown.arrow-down.prevent="show()">
    <button type="button" class="ix-date__open" x-on:click="open ? hide() : show()" aria-haspopup="dialog" x-bind:aria-expanded="open ? 'true' : 'false'" aria-label="Choose a date from the calendar"></button>

    <div class="ix-cal" x-ref="panel" x-show="open" x-cloak x-transition:enter="ix-pop-in" x-transition:enter-start="ix-pop-in--from" x-transition:enter-end="ix-pop-in--to" role="dialog" aria-label="Choose a date" x-on:keydown="key($event)">
        <div class="ix-cal__head">
            <button type="button" class="ix-cal__nav ix-cal__nav--prev" x-on:click="step(-1)" aria-label="Previous month"></button>
            <strong x-text="monthLabel" aria-live="polite"></strong>
            <button type="button" class="ix-cal__nav ix-cal__nav--next" x-on:click="step(1)" aria-label="Next month"></button>
        </div>
        <div class="ix-cal__grid" role="group" aria-label="Days">
            <template x-for="name in names" x-bind:key="name"><span class="ix-cal__dow" aria-hidden="true" x-text="name"></span></template>
            <template x-for="d in days" x-bind:key="d.iso">
                <button type="button" class="ix-cal__day" x-bind:data-iso="d.iso" x-text="d.day"
                    x-bind:class="{ 'is-out': !d.inMonth, 'is-today': d.today, 'is-selected': d.selected }"
                    x-bind:aria-disabled="d.disabled ? 'true' : null" x-bind:aria-label="label(d)" x-bind:aria-pressed="d.selected ? 'true' : 'false'"
                    x-bind:aria-current="d.today ? 'date' : null" x-bind:tabindex="d.iso === focused ? 0 : -1" x-on:click="choose(d)"></button>
            </template>
        </div>
        <div class="ix-cal__foot">
            <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" x-on:click="clear()">Clear</button>
            <button type="button" class="ix-btn ix-btn--sm" x-on:click="now()">Today</button>
        </div>
    </div>
</div>
