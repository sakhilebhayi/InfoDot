{{-- A multi-line control for form.field. --}}
@props(['name', 'id' => null, 'value' => null])
@php($id = $id ?? $name)
<textarea {{ $attributes->merge(['name' => $name, 'id' => $id, 'class' => 'ix-textarea', 'rows' => 4]) }}
    aria-describedby="{{ $id }}-msg" @if ($errors->has($name)) aria-invalid="true" @endif>{{ old($name, $value) }}</textarea>
