@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'rounded-md border-[var(--line)] text-[var(--ink)] px-4 py-2.5 text-sm leading-6 shadow-sm focus:border-[var(--gold)] focus:ring focus:ring-[rgba(240,188,46,0.2)]']) !!}>
