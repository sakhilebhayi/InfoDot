<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @include('partials.head-icons')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · InfoDot' : 'InfoDot' }}</title>
    @include('partials.head-fonts', ['fonts' => ['dm-sans', 'icons']])
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --paper: #f6f7f9;
            --paper-soft: #eceef1;
            --ink: #16212c;
            --ink-soft: #4c5c6c;
            --charcoal: #24272b;
            --gold: #f0bc2e;
            --gold-deep: #d9a30f;
            --line: rgba(22, 33, 44, 0.11);
            --font-display: 'Baloo 2', system-ui, sans-serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-mono: 'Space Mono', ui-monospace, monospace;
        }
        html { background: var(--paper); }
        body { background: var(--paper); }
        .font-display { font-family: var(--font-display); }
        .font-mono { font-family: var(--font-mono); }
    </style>
</head>
<body>
    <div class="font-sans text-[var(--ink)] antialiased" style="font-family: var(--font-body);">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
