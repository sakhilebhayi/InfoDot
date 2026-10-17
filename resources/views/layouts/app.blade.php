<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ \App\Support\NavSection::current() }} · InfoDot</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400..700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts below). A second copy fights it. --}}
</head>
<body class="ix" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <a href="#main" class="ix-skip">Skip to content</a>
    <x-jet-banner />

    <div class="ix-shell">
        @include('layouts.sidebar')

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
