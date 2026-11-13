<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark">
    @include('partials.head-icons')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · InfoDot' : 'InfoDot' }}</title>
    @include('partials.head-fonts', ['fonts' => ['archivo', 'stencil', 'icons']])
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- The way in (sign in, create an account, reset, verify, confirm, sign out): the same top bar, band and ground as
     every other page, so the first screen a person sees is already The Yard. Steel is the colour of people and
     administration; an account is both. No photograph and no motion: these pages are for getting through. --}}
<body class="ix" data-area="people">
    <a href="#main" class="ix-skip">Skip to content</a>

    <div class="ix-shell">
        <header class="ix-top ix-top--public">
            <div class="ix-top__inner">
                <a href="{{ url('/') }}" class="ix-brand" aria-label="InfoDot home">
                    <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/brand/logo_white.png') }}" alt="InfoDot" width="64" height="26">
                </a>

                <div class="ix-top__actions">
                    @guest
                        @if (Route::has('login') && ! request()->routeIs('login'))<a href="{{ route('login') }}" class="ix-btn">Sign in</a>@endif
                        @if (Route::has('register') && ! request()->routeIs('register'))<a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Create account</a>@endif
                    @endguest
                </div>
            </div>
        </header>

        <div class="ix-band">
            <div class="ix-band__inner">
                <span class="ix-band__code">ACCT</span>
                <nav class="ix-crumbs" aria-label="Breadcrumb">
                    <a href="{{ url('/') }}">InfoDot</a>
                    <span class="material-symbols-rounded sep" aria-hidden="true">chevron_right</span>
                    <span class="here" aria-current="page">{{ $title ?? 'Account' }}</span>
                </nav>
            </div>
        </div>

        <main id="main" class="ix-page ix-entry">{{ $slot }}</main>

        @include('layouts.app-footer')
    </div>

    @livewireScripts
</body>
</html>
