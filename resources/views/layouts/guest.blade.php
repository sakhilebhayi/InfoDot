<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark">
    @include('partials.head-icons')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · InfoDot' : 'InfoDot' }}</title>
    @include('partials.head-dot-ui', ['part' => 'css'])
    @include('partials.head-fonts', ['fonts' => ['icons']])
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.head-dot-ui', ['part' => 'script'])
</head>
{{-- The way in (sign in, create an account, reset, verify, confirm, sign out): the same top bar, band and ground as
     every other page, so the first screen a person sees is already The Yard. Steel is the colour of people and
     administration; an account is both. No photograph and no motion: these pages are for getting through. --}}
<body class="ix" data-area="people">
    <a href="#main" class="ix-skip">Skip to content</a>

    <div class="ix-shell">
        <x-ix.top-public way :search="false" />

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
