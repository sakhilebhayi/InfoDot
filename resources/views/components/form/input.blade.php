{{-- A text-like control for form.field: same id as the field's, old value restored, invalid and described-by set from the validation errors. --}}
@props(['name', 'type' => 'text', 'id' => null, 'value' => null])
@php($id = $id ?? $name)
<input {{ $attributes->merge(['type' => $type, 'name' => $name, 'id' => $id, 'class' => 'ix-input']) }}
    @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
    aria-describedby="{{ $id }}-msg" @if ($errors->has($name)) aria-invalid="true" @endif>
