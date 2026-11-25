{{-- One checkbox with its words. A hidden 0 is sent first so an unticked box still reports itself, as the booking settings form expects. --}}
@props(['name', 'label', 'value' => '1', 'checked' => false, 'hint' => null, 'id' => null, 'withOff' => false])
@php($id = $id ?? $name)
<label class="ix-check" for="{{ $id }}">
    @if ($withOff)<input type="hidden" name="{{ $name }}" value="0">@endif
    <input {{ $attributes->merge(['type' => 'checkbox', 'name' => $name, 'id' => $id, 'value' => $value]) }} @checked(old($name, $checked) == $value) @if ($errors->has($name)) aria-invalid="true" @endif>
    <span class="ix-check__text"><b>{{ $label }}</b>@if ($hint)<span class="ix-hint">{{ $hint }}</span>@endif</span>
</label>
