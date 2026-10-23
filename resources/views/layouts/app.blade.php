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
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Big+Shoulders+Stencil+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts below). A second copy fights it. --}}
</head>
@php([$area, $areaCode] = \App\Support\NavSection::area())
@php($company = auth()->user()?->currentTeam)
<body class="ix" data-area="{{ $area }}">
    <a href="#main" class="ix-skip">Skip to content</a>
    <x-jet-banner />

    <div class="ix-shell">
        @livewire('navigation-menu')

        @unless ($area === 'home')
            <div class="ix-band">
                <div class="ix-band__inner">
                    <span class="ix-band__code">{{ $areaCode }}</span>
                    <nav class="ix-crumbs" aria-label="Breadcrumb">
                        @foreach (\App\Support\NavSection::crumbs() as [$label, $url])
                            @if (! $loop->first)<span class="material-symbols-rounded sep" aria-hidden="true">chevron_right</span>@endif
                            @if ($loop->last)
                                <span class="here" aria-current="page">{{ $label }}</span>
                            @elseif ($url)
                                <a href="{{ $url }}">{{ $label }}</a>
                            @else
                                <span>{{ $label }}</span>
                            @endif
                        @endforeach
                    </nav>
                </div>
            </div>
            @if ($area === 'knowledge')
                <nav class="ix-subnav" aria-label="Knowledge">
                    <div class="ix-subnav__inner">
                        <a href="{{ route('questions') }}" @if(request()->routeIs('questions*', 'seek')) aria-current="page" @endif>Questions</a>
                        <a href="{{ route('solutions') }}" @if(request()->routeIs('solutions*', 'add')) aria-current="page" @endif>Solutions</a>
                        <a href="{{ route('topics') }}" @if(request()->routeIs('topics*')) aria-current="page" @endif>Topics</a>
                        <a href="{{ route('library') }}" @if(request()->routeIs('library')) aria-current="page" @endif>Library</a>
                    </div>
                </nav>
            @endif
        @endunless

        <main id="main" class="ix-page" @if($area === 'home') style="padding-top:0;max-width:none;padding-left:0;padding-right:0" @endif>{{ $slot }}</main>

        @include('layouts.app-footer')
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
