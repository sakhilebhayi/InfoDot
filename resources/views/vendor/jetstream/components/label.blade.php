@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium leading-6 text-[var(--ink-soft)]']) }}>
    {{ $value ?? $slot }}
</label>
