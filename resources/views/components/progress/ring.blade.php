{{--
    A circular progress indicator (R-64 phase 5) with the percentage in the middle. Real figures only: with no positive `max` nothing is drawn. `size` is the
    diameter in pixels (default 72). The ring is a single circle whose dash length is the percentage, so it animates with no script.
--}}
@props(['label', 'value', 'max' => 100, 'size' => 72, 'tone' => null, 'caption' => null])
@php
    $percent = \App\Support\Progress::percent((float) $value, (float) $max);
@endphp
@if ($percent !== null)
    <div {{ $attributes->class(['ix-ring', 'ix-prog--'.$tone => $tone]) }} style="--ring: {{ (int) $size }}px">
        <div class="ix-ring__dial" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ (float) $max }}" aria-valuenow="{{ min((float) $max, max(0.0, (float) $value)) }}" aria-valuetext="{{ $percent }}%">
            <svg viewBox="0 0 36 36" aria-hidden="true" focusable="false">
                <circle class="ix-ring__track" cx="18" cy="18" r="15.9155" fill="none" pathLength="100"></circle>
                <circle class="ix-ring__fill" cx="18" cy="18" r="15.9155" fill="none" pathLength="100" stroke-dasharray="{{ $percent }} 100"></circle>
            </svg>
            <span class="ix-ring__num">{{ $percent }}<small>%</small></span>
        </div>
        @if ($caption)<span class="ix-ring__cap">{{ $caption }}</span>@endif
    </div>
@endif
