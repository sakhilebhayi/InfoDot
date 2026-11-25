{{--
    A labelled field (R-64 phase 3): the label, the control (the slot), then one reserved line for the hint or the error, so a message never
    moves what is below it. Pass the field's `name` and the error for it is found automatically; the control inside should use the same id
    (the form.* controls do this for you) so the label, the hint and the error are all tied to it for screen readers.
--}}
@props(['name', 'label', 'hint' => null, 'optional' => false, 'id' => null])
@php
    $id = $id ?? $name;
    $error = $errors->first($name);
@endphp
<div {{ $attributes->class(['ix-field', 'has-error' => $error !== '']) }}>
    <label class="ix-label" for="{{ $id }}">{{ $label }}@if ($optional)<span class="ix-label__flag">Optional</span>@endif</label>
    {{ $slot }}
    <div class="ix-field__foot" id="{{ $id }}-msg">
        @if ($error !== '')
            <p class="ix-error" role="alert">{{ $error }}</p>
        @elseif ($hint)
            <p class="ix-hint">{{ $hint }}</p>
        @endif
    </div>
</div>
