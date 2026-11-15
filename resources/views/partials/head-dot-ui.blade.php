{{-- The shared Dot header, search, switcher styles and Roboto, from this site's own public/dot-ui (dot-ui 0.6.1, R-52): one minified
     stylesheet, FIRST in the head and before the host CSS ($part = 'css'), and one deferred script, AFTER the host's stylesheet ($part = 'script'):
     a script placed between two stylesheets lets Chrome paint the page with the first one only, which showed an unstyled page and then shifted
     it (measured, CLS 0.2 on a phone). No CDN, no inline script, no inline style; `class="dot-roboto"` is on <html>. The nonce is the page's
     CSP nonce, which the lazy loader copies. --}}
@if (($part ?? 'css') === 'css')
<link rel="stylesheet" href="{{ asset('dot-ui/css/dot-ui.min.css') }}">
@if ($preload ?? true)
{{-- Roboto (Latin) is preloaded on the app pages, where the largest paint is text and the swap would reflow it. Not on the landing page, whose
     largest paint is the hero photograph (docs/PERFORMANCE.md: a preload there slows it). crossorigin is required even same-origin. --}}
<link rel="preload" as="font" type="font/woff2" href="{{ asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2') }}" crossorigin>
@endif
@else
<script src="{{ asset('dot-ui/js/dot-ui-core.min.js') }}" defer nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
@endif
