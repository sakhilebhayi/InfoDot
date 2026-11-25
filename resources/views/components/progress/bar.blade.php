{{--
    A linear progress indicator (R-64 phase 5): a bar with a label and a figure. `value` and `max` must come from real data (see App\Support\Progress); with no
    positive `max` nothing is drawn. `valueText` words the figure ("3 of 4 done"); without it the percentage shows. `size="sm"` is the compact form for
    dashboard cards and table cells, `hide-label` keeps the label for screen readers only, `tone` is ok or warn. `live` adds a polite announcement that is
    re-read when a Livewire update changes the figure. The fill grows with a transform, so changing it never moves the page.
--}}
@props(['label', 'value', 'max' => 100, 'valueText' => null, 'showValue' => true, 'hideLabel' => false, 'size' => 'md', 'tone' => null, 'live' => false])
@php
    $percent = \App\Support\Progress::percent((float) $value, (float) $max);
    $uid = 'prog-'.substr(md5($label.$value.$max.random_int(0, 9999)), 0, 8);
    $text = $valueText ?? ($percent.'%');
    $spoken = $valueText ? $valueText.' ('.$percent.'%)' : $percent.'%';
@endphp
@if ($percent !== null)
    <div {{ $attributes->class(['ix-prog', 'ix-prog--'.$size, 'ix-prog--'.$tone => $tone]) }}>
        <div class="ix-prog__row">
            <span @class(['ix-prog__label', 'ix-sr' => $hideLabel]) id="{{ $uid }}">{{ $label }}</span>
            @if ($showValue && ! $hideLabel)<span class="ix-prog__val">{{ $text }}</span>@endif
        </div>
        <div class="ix-prog__track" role="progressbar" aria-labelledby="{{ $uid }}" aria-valuemin="0" aria-valuemax="{{ (float) $max }}" aria-valuenow="{{ min((float) $max, max(0.0, (float) $value)) }}" aria-valuetext="{{ $spoken }}">
            <span class="ix-prog__fill" style="--p: {{ $percent / 100 }}"></span>
        </div>
        @if ($live)<span class="ix-sr" role="status" aria-live="polite">{{ $label }}: {{ $spoken }}</span>@endif
    </div>
@endif
