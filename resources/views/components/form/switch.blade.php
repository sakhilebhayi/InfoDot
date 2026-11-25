{{-- A switch: a checkbox that means "on or off" and takes effect when the form is saved. Its words are tied to it with aria-labelledby. --}}
@props(['name', 'label', 'value' => '1', 'checked' => false, 'hint' => null, 'id' => null])
@php($id = $id ?? $name)
<label class="ix-check" for="{{ $id }}">
    <input type="hidden" name="{{ $name }}" value="0">
    <input {{ $attributes->merge(['type' => 'checkbox', 'role' => 'switch', 'name' => $name, 'id' => $id, 'value' => $value, 'class' => 'ix-switch']) }} @checked(old($name, $checked) == $value)>
    <span class="ix-check__text"><b>{{ $label }}</b>@if ($hint)<span class="ix-hint">{{ $hint }}</span>@endif</span>
</label>
