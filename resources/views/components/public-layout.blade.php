@props(['title' => 'InfoDot', 'band' => null, 'crumb' => null, 'description' => null, 'landing' => false, 'motion' => null, 'reveal' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    @php($fullTitle = $title === 'InfoDot' ? 'InfoDot: free business answers and connected tools' : $title.' · InfoDot')
    @php($metaDescription = $description ?? 'InfoDot brings together practical business knowledge, people who share what works, and digital tools that help you start, manage and grow your business.')
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    {{-- One address per page (R-17 §13): the canonical strips query strings so filters and trackers never split the page's identity. --}}
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="InfoDot">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    @include('partials.head-icons')
    @include('partials.head-dot-ui', ['part' => 'css', 'preload' => ! $landing])
    @include('partials.head-fonts', ['fonts' => ['icons']])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.head-dot-ui', ['part' => 'script'])
    @if ($landing || $motion || $reveal)
        {{-- Motion is added on top of a page that is complete without it: the flag is set before first paint only when the visitor has not asked for reduced motion. --}}
        {{-- Items that animate in start hidden. The timer here, not the motion script, brings them back: if that script is slow, blocked
             or broken, the page shows complete after 1.2 seconds instead of staying blank until it arrives. --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) { document.documentElement.classList.add('has-motion'); window.__motionFailsafe = window.setTimeout(function () { document.documentElement.classList.add('motion-failsafe'); }, 1200); }</script>
        {!! \App\Support\OptionalVite::tags($motion ? 'resources/js/'.$motion.'.js' : ($reveal ? 'resources/js/pages.js' : 'resources/js/landing.js')) !!}
    @endif
    @livewireStyles
</head>
{{-- The shell for pages a visitor who is not signed in may open: the same top bar, band and footer as the app, without the company and the app tabs. --}}
<body class="ix" data-area="{{ $landing ? 'home' : ($motion ? 'platforms' : 'knowledge') }}">
    <a href="#main" class="ix-skip">Skip to content</a>

    <div class="ix-shell">
        <x-ix.top-public :landing="$landing" />

        @if ($band)
            <div class="ix-band">
                <div class="ix-band__inner">
                    <span class="ix-band__code">{{ $band }}</span>
                    <nav class="ix-crumbs" aria-label="Breadcrumb">
                        <a href="{{ url('/') }}">InfoDot</a>
                        <span class="material-symbols-rounded sep" aria-hidden="true">chevron_right</span>
                        <span class="here" aria-current="page">{{ $crumb ?? $title }}</span>
                    </nav>
                </div>
            </div>
        @endif

        <main id="main" @class(['ix-page' => ! $landing && ! $motion])>{{ $slot }}</main>

        @include('layouts.app-footer')
    </div>

    @livewireScripts
</body>
</html>
