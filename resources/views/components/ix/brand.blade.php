{{-- InfoDot's own logo for the shared header's brand slot (the original file, never redrawn). $fallback: print the name when the build
     does not hold the logo (error pages must open without a build). The header sets its height (--dh-logo-h in infodot.css). --}}
@props(['fallback' => false])
@if ($fallback && ! ($logo = \App\Support\OptionalVite::asset('resources/img/brand/logo_white.png')))
    <strong class="ix-brand__name">InfoDot</strong>
@else
    <img src="{{ $logo ?? \Illuminate\Support\Facades\Vite::asset('resources/img/brand/logo_white.png') }}" alt="" width="64" height="26">
@endif
