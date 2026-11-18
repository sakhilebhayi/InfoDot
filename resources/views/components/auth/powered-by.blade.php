{{-- "Powered by" and InfoDot's own yellow-and-white logo (the original file), bottom centre, whenever a platform started this
     sign-in. A graphite strip in normal flow after the page: it never overlaps the form, it reads the same on every platform
     theme, and it is never drawn on a direct visit to InfoDot (the layout only includes it when a platform is resolved). --}}
<footer class="ix-powered">
    <span>Powered by</span>
    @if ($logo = \App\Support\OptionalVite::asset('resources/img/brand/logo_white.png'))
        <img src="{{ $logo }}" alt="InfoDot" width="96" height="39">
    @else
        <strong>InfoDot</strong>
    @endif
</footer>
