<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dot-roboto">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? \App\Support\NavSection::current() }} · InfoDot</title>
    @stack('meta')
    @include('partials.head-icons')
    @include('partials.head-dot-ui', ['part' => 'css'])
    @include('partials.head-fonts', ['fonts' => ['icons']])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.head-dot-ui', ['part' => 'script'])
    @livewireStyles
    {{-- No stand-alone Alpine here: Livewire 3 bundles and starts its own (@livewireScripts below). A second copy fights it. --}}
</head>
@php([$area, $areaCode] = \App\Support\NavSection::area())
@php($company = auth()->user()?->currentTeam)
<body class="ix" data-area="{{ $area }}">
    <a href="#main" class="ix-skip">Skip to content</a>
    <x-jet-banner />

    <div class="ix-shell">
        @auth
            {{-- R-52: plain Blade, not Jetstream's Livewire navigation-menu: a header drawn inside a Livewire component gets morph markers inside the
                 drawer's slot, which the shared component reads as "custom drawer markup" and prints no menu. The search and the Dot Apps
                 switcher inside it stay Livewire components. --}}
            @include('navigation-menu')
        @else
            {{-- Public business pages and the directory open without signing in (R-33). --}}
            @include('layouts.guest-top')
        @endauth

        @auth
            @php($licenceBanner = app(\App\Services\Licensing\LicenceBanner::class)->for($company, auth()->user()))
            @if ($licenceBanner)
                <div class="ix-page" style="flex: none; padding-top: 16px; padding-bottom: 0;" data-licence-banner="{{ $licenceBanner['tone'] }}">
                    <p class="ix-alert" role="status" style="margin: 0;">{{ $licenceBanner['text'] }} <a href="{{ route('teams.show', $company) }}#seats" style="text-decoration: underline;">{{ __('See licences') }}</a></p>
                </div>
            @endif
        @endauth

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
        @auth
            <x-ix.toasts :url="route('notices.arrivals')" :last="app(\App\Services\Knowledge\InAppNotices::class)->bellSummary(auth()->user())['latest']" />
        @endauth

        @include('layouts.app-footer')
    </div>

    @stack('modals')
    @livewireScripts
</body>
</html>
