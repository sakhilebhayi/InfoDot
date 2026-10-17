@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'ix-input disabled:cursor-not-allowed disabled:opacity-60']) !!}>
