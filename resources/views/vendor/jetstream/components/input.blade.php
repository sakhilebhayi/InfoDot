@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'rounded-md border-[var(--line)] text-[var(--ink)] px-4 py-2.5 text-sm leading-6 shadow-sm focus:border-[var(--blue)] focus:ring focus:ring-[var(--blue)]/20']) !!}>
