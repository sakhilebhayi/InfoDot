{{-- The icon font this page draws above the fold, asked for before the stylesheet has been read (R-49). It is served from this
     site and declared in resources/css/fonts.css; this only brings it forward, because the icons block until it arrives. Text is
     Roboto from /dot-ui (R-52), which is NOT preloaded on purpose: dot-ui's own measurements (docs/PERFORMANCE.md) show a preload
     slowing the largest paint. $fonts names what to preload: 'icons'. A font the build does not hold is left out, and the page
     still opens. --}}
@foreach ($fonts as $font)
@if ($href = \App\Support\OptionalVite::asset(\App\Support\OptionalVite::PRELOADABLE_FONTS[$font]))
<link rel="preload" as="font" type="font/woff2" href="{{ $href }}" crossorigin>
@endif
@endforeach
