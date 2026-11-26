<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark">
    @include('partials.head-icons')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (($platformBranding ?? null))<meta name="robots" content="noindex, nofollow">@endif
    <title>{{ $title ? $title.' · InfoDot' : 'InfoDot' }}</title>
    @include('partials.head-dot-ui', ['part' => 'css'])
    @include('partials.head-fonts', ['fonts' => ['icons']])
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.head-dot-ui', ['part' => 'script'])
</head>
{{-- The way in (sign in, create an account, reset, verify, confirm, sign out): the same top bar, band and ground as
     every other page, so the first screen a person sees is already The Yard. Steel is the colour of people and
     administration; an account is both. No photograph and no motion: these pages are for getting through.
     R-54: when a registered platform started the sign-in ($platformBranding, resolved server-side), the page is the
     platform's: its colour, logo and real screen beside the form, and InfoDot's "Powered by" strip at the bottom. --}}
@php($brand = $platformBranding ?? null)
<body class="ix{{ $brand ? ' ix-auth-body' : '' }}" data-area="people"@if ($brand) data-brand-mode="{{ $brand->mode }}" style="{{ $brand->cssVariables() }}"@endif>
    <a href="#main" class="ix-skip">Skip to content</a>

    @if ($brand)
        @include('partials.cookie-banner')
        <div class="ix-auth">
            <x-auth.brand-panel :brand="$brand" />
            <main id="main" class="ix-auth__main">{{ $slot }}</main>
        </div>
        <x-auth.powered-by />
    @else
    <div class="ix-shell">
        <x-ix.top-public way :search="false" />
        @include('partials.cookie-banner')

        @if ($panel ?? null)
            {{-- R-69: InfoDot's own sign-in pages are split: a photograph with InfoDot's message on one half, the form on the other.
                 On a phone the photograph is a short band above the form and the points follow it. --}}
            <div class="ix-sa" data-panel="{{ $panel }}">
                <x-auth.infodot-panel :kind="$panel" />
                <main id="main" class="ix-sa__main ix-entry">{{ $slot }}</main>
            </div>
        @else
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
        @endif

        @include('layouts.app-footer')
    </div>
    @endif

    @livewireScripts
</body>
</html>
