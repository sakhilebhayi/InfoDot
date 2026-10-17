<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Support\NavSection::current() }} · InfoDot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts below). A second copy fights it. --}}
</head>
<body class="ix" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <a href="#main" class="ix-skip">Skip to content</a>
    <x-jet-banner />

    <div class="ix-shell">
        <aside class="ix-rail" :class="{ 'is-open': nav }" aria-label="Primary">
            <div class="ix-brand">
                <span class="ix-brand__mark"><span class="material-symbols-rounded" aria-hidden="true">hub</span></span>
                <span class="ix-brand__name">InfoDot</span>
            </div>

            <nav class="ix-nav">
                <div class="ix-nav__group">Work</div>
                <a href="{{ route('questions') }}" class="ix-nav__link" @if(request()->routeIs('questions*', 'seek')) aria-current="page" @endif>
                    <span class="material-symbols-rounded" aria-hidden="true">help</span>Questions
                </a>
                <a href="{{ route('solutions') }}" class="ix-nav__link" @if(request()->routeIs('solutions*', 'add')) aria-current="page" @endif>
                    <span class="material-symbols-rounded" aria-hidden="true">checklist</span>Solutions
                </a>

                <div class="ix-nav__group">Ecosystem</div>
                <a href="{{ route('ecosystem.widget') }}" class="ix-nav__link" @if(request()->routeIs('ecosystem.widget')) aria-current="page" @endif>
                    <span class="material-symbols-rounded" aria-hidden="true">apps</span>Platforms
                </a>
                <a href="{{ route('analytics.dashboard') }}" class="ix-nav__link" @if(request()->routeIs('analytics.*')) aria-current="page" @endif>
                    <span class="material-symbols-rounded" aria-hidden="true">monitoring</span>Analytics
                </a>

                <div class="ix-nav__group">Account</div>
                <a href="{{ route('profile.show', auth()->id()) }}" class="ix-nav__link" @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                    <span class="material-symbols-rounded" aria-hidden="true">person</span>Profile
                </a>
                <a href="{{ route('profile.edit') }}" class="ix-nav__link">
                    <span class="material-symbols-rounded" aria-hidden="true">tune</span>Settings
                </a>
            </nav>

            @auth
            <div class="ix-rail__foot">
                <span class="ix-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                <div class="ix-rail__who">
                    <b>{{ Auth::user()->name }}</b>
                    <span>{{ Auth::user()->currentTeam->name ?? 'Personal' }}</span>
                </div>
            </div>
            @endauth
        </aside>

        <div class="ix-scrim" :class="{ 'is-open': nav }" @click="nav = false"></div>

        <div class="ix-main">
            @livewire('navigation-menu')

            <main id="main" class="ix-page">{{ $slot }}</main>

            @include('layouts.app-footer')
        </div>
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
