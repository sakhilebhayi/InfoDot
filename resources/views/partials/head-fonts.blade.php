{{-- The fonts this page draws above the fold, asked for before the stylesheet has been read (R-49). Every font is
     served from this site and declared in resources/css/fonts.css; this only brings forward the ones that would
     otherwise make the first screen re-draw. $fonts names them: 'archivo', 'stencil', 'dm-sans', 'icons'.
     A font the build does not hold is left out, and the page still opens. --}}
@foreach ($fonts as $font)
@if ($href = \App\Support\OptionalVite::asset(\App\Support\OptionalVite::PRELOADABLE_FONTS[$font]))
<link rel="preload" as="font" type="font/woff2" href="{{ $href }}" crossorigin>
@endif
@endforeach
