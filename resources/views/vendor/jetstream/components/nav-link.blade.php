@props(['active'])

<a {{ $attributes->merge(['class' => 'ix-nav__link']) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
