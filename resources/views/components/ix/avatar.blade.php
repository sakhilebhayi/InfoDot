{{-- A person, shown the same way everywhere: their profile picture when there is one that can load, otherwise the first letter of their name. --}}
@props(['user', 'size' => null, 'square' => false, 'alt' => ''])
@php
    $photo = $user?->photoUrl();
    $style = $size ? 'width: '.(int) $size.'px; height: '.(int) $size.'px; font-size: '.max(11, (int) round($size * 0.38)).'px;' : null;
@endphp
<span {{ $attributes->class(['ix-avatar', 'ix-avatar--sq' => $square])->merge($style ? ['style' => $style] : []) }}>@if ($photo)<img src="{{ $photo }}" alt="{{ $alt }}">@else{{ $user?->initial() ?? '?' }}@endif</span>
