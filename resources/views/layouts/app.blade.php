<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f78c8">
    <title>{{ \App\Support\NavSection::current() }} · InfoDot</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts below). A second copy fights it. --}}
</head>
@php($band = \App\Support\NavSection::band())
<body class="ix">
    <a href="#main" class="ix-skip">Skip to content</a>
    <x-jet-banner />

    <div class="ix-shell">
        @livewire('navigation-menu')

        <div class="ix-band {{ $band['compact'] ? 'ix-band--compact' : '' }}">
            <div class="ix-band__inner">
                <div class="ix-band__row">
                    <div>
                        @if ($band['compact'])
                            <div class="ix-band__title">{{ $band['title'] }}</div>
                        @else
                            <h1>{{ $band['title'] }}</h1>
                        @endif
                        @if ($band['lede'])
                            <p>{{ $band['lede'] }}</p>
                        @endif
                    </div>
                    @if ($band['action'])
                        <a href="{{ $band['action']['url'] }}" class="ix-btn ix-btn--gold">
                            <span class="material-symbols-rounded" aria-hidden="true">{{ $band['action']['icon'] }}</span>{{ $band['action']['label'] }}
                        </a>
                    @endif
                </div>
                @if ($band['search'])
                    <livewire:search />
                @endif
            </div>
        </div>

        <main id="main" class="ix-page">{{ $slot }}</main>

        @include('layouts.app-footer')
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
