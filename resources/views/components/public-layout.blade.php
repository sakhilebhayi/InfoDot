@props(['title' => 'InfoDot', 'band' => 'KNOW', 'crumb' => 'Search'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title }} · InfoDot</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Big+Shoulders+Stencil+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- The shell for pages a visitor who is not signed in may open: the same top bar, band and footer as the app, without the company and the tabs. --}}
<body class="ix" data-area="knowledge">
    <a href="#main" class="ix-skip">Skip to content</a>

    <div class="ix-shell">
        <header class="ix-top">
            <div class="ix-top__inner">
                <a href="{{ url('/') }}" class="ix-brand" aria-label="InfoDot home">
                    <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot" width="64" height="26">
                </a>

                <div class="ix-top__search">
                    <livewire:search />
                </div>

                <div class="ix-top__actions">
                    @if (Route::has('login'))<a href="{{ route('login') }}" class="ix-btn">Sign in</a>@endif
                    @if (Route::has('register'))<a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Create account</a>@endif
                </div>
            </div>
        </header>

        <div class="ix-band">
            <div class="ix-band__inner">
                <span class="ix-band__code">{{ $band }}</span>
                <nav class="ix-crumbs" aria-label="Breadcrumb">
                    <a href="{{ url('/') }}">InfoDot</a>
                    <span class="material-symbols-rounded sep" aria-hidden="true">chevron_right</span>
                    <span class="here" aria-current="page">{{ $crumb }}</span>
                </nav>
            </div>
        </div>

        <main id="main" class="ix-page">{{ $slot }}</main>

        @include('layouts.app-footer')
    </div>

    @livewireScripts
</body>
</html>
