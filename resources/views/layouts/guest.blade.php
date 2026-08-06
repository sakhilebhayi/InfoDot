<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="theme-color" content="#f6f7f9" />
    <link rel="shortcut icon" href="{{ asset('img/icons/icon.png') }}" />
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('img/icons/icon.png') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>InfoDot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" rel="stylesheet">
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
    <script>
        function toggleNavbar(collapseID) {
            document.getElementById(collapseID).classList.toggle("hidden");
            document.getElementById(collapseID).classList.toggle("block");
        }
    </script>
</body>
</html>
