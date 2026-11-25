{{--
    A file field with a drop area (R-64 phase 4). The real <input type="file"> stays in the page and submits with the form; controls.js adds dropping,
    a list with image previews and a remove button, and checks the kind and size before anything is sent (the server still checks them). `maxMb` and
    `accept` (as on a file input) set the limits; `multiple` allows several, up to `max`. Extra attributes (wire:model, required) go to the file input. Use inside form.field.
--}}
@props(['name', 'id' => null, 'accept' => null, 'maxMb' => 0, 'multiple' => false, 'max' => 0, 'prompt' => 'Choose a file', 'drop' => 'or drop it here'])
@php($id = $id ?? $name)
<div class="ix-drop" wire:ignore x-data="ixDropzone({ accept: @js((string) $accept), maxBytes: {{ (int) round($maxMb * 1048576) }}, multiple: @js((bool) $multiple), max: {{ (int) $max }} })"
     x-bind:class="{ 'is-ready': ready, 'is-over': over, 'has-files': files.length > 0 }" x-on:dragover.prevent="over = true"
     x-on:dragleave.prevent="if (! $el.contains($event.relatedTarget)) over = false" x-on:drop.prevent="drop($event)">
    <input {{ $attributes->merge(['class' => 'ix-input ix-drop__input']) }} type="file" x-ref="input" name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $id }}" @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif
        aria-describedby="{{ $id }}-msg" @if ($errors->has($name) || $errors->has($name.'.*')) aria-invalid="true" @endif x-on:change="picked()">
    <div class="ix-drop__ui" x-show="ready" x-cloak>
        <div class="ix-drop__prompt">
            <button type="button" class="ix-btn ix-btn--sm" x-on:click="$refs.input.click()">{{ $prompt }}</button>
            <span class="ix-hint">{{ $drop }}@if ($maxMb > 0) &middot; up to {{ rtrim(rtrim(number_format($maxMb, 1), '0'), '.') }} MB @endif</span>
        </div>
        <ul class="ix-drop__list" x-show="files.length > 0">
            <template x-for="(f, i) in files" x-bind:key="f.name + i">
                <li>
                    <span class="ix-drop__thumb" aria-hidden="true"><img x-show="f.url" x-bind:src="f.url" alt=""></span>
                    <span class="ix-drop__name"><span x-text="f.name"></span> <small x-text="human(f.size)"></small></span>
                    <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" x-on:click="remove(i)" x-bind:aria-label="'Remove ' + f.name">Remove</button>
                </li>
            </template>
        </ul>
        <p class="ix-error" role="alert" x-show="error" x-text="error"></p>
    </div>
</div>
