{{-- A select for form.field. Pass the options as the slot (<option> elements), or `options` as value => label and the chosen one as `value`. --}}
@props(['name', 'id' => null, 'options' => null, 'value' => null, 'placeholder' => null])
@php
    $id = $id ?? $name;
    $chosen = (string) old($name, $value);
@endphp
<select {{ $attributes->merge(['name' => $name, 'id' => $id, 'class' => 'ix-select']) }}
    aria-describedby="{{ $id }}-msg" @if ($errors->has($name)) aria-invalid="true" @endif>
    @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
    @if ($options !== null)
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $optionValue === $chosen)>{{ $optionLabel }}</option>
        @endforeach
    @else
        {{ $slot }}
    @endif
</select>
