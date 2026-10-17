@props(['value'])

<label {{ $attributes->merge(['class' => 'ix-label']) }}>
    {{ $value ?? $slot }}
</label>
