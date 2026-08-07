<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $code }} — @yield('title') · InfoDot</title>
        <meta name="robots" content="noindex">

        <link rel="icon" href="{{ asset('favicon.ico') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

        @php
            $viteManifest = public_path('build/manifest.json');

            // Every Dot platform, pulled from the shared ecosystem registry
            // (config/ecosystem.php, identical across all platforms) rather
            // than a fixed hand-picked subset -- add a platform to the
            // registry once and it shows up here automatically everywhere.
            $currentPlatformName = 'InfoDot';
            $discover = collect(config('ecosystem.platforms', []))
                ->reject(fn ($p) => ($p['name'] ?? null) === $currentPlatformName)
                ->reject(fn ($p) => ($p['active'] ?? true) === false)
                ->values()
                ->all();
        @endphp
        @if (file_exists($viteManifest))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            :root {
                --paper: #f6f7f9;
                --paper-soft: #e7eaef;
                --ink: #16212c;
                --ink-soft: #4c5c6c;
                --charcoal: #24272b;
                --charcoal-soft: #34383e;
                --gold: #f0bc2e;
                --gold-deep: #d9a30f;
                --blue-dot: #2fa8e0;
                --coral: #f47272;
                --green: #4fce8e;
                --line: rgba(22, 33, 44, 0.11);
                --line-dark: rgba(246, 247, 249, 0.14);
                --font-display: 'Baloo 2', system-ui, sans-serif;
                --font-body: 'DM Sans', system-ui, sans-serif;
                --font-mono: 'Space Mono', ui-monospace, monospace;
                --ease-out: cubic-bezier(0.23, 1, 0.32, 1);
            }
            * { box-sizing: border-box; }
            html { background: var(--paper); }
            body { margin: 0; font-family: var(--font-body); background: var(--paper); color: var(--ink); min-height: 100dvh; display: flex; flex-direction: column; }
            .font-display { font-family: var(--font-display); }
            .font-mono { font-family: var(--font-mono); }
            a { color: inherit; }
            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }
            .link-underline { background-image: linear-gradient(currentColor, currentColor); background-position: 0 100%; background-repeat: no-repeat; background-size: 0% 1px; transition: background-size 200ms var(--ease-out); }
            @media (hover: hover) and (pointer: fine) {
                .link-underline:hover { background-size: 100% 1px; }
            }
        </style>
    </head>
    <body>
        <header style="background: var(--charcoal);">
            <nav style="max-width: 1400px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;">
                <a href="/" class="press" style="display: flex; align-items: center; gap: 10px;">
                    <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot" style="height: 40px; width: auto;">
                </a>
                <div style="display: flex; align-items: center; gap: 12px;">
                    @auth
                        <a href="{{ route('dashboard') }}" class="press" style="padding: 10px 20px; background: var(--gold); color: var(--charcoal); font-family: var(--font-display); font-weight: 600; font-size: 14px; border-radius: 999px; text-decoration: none;">Dashboard</a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="link-underline" style="color: rgba(246,247,249,0.85); font-size: 14px; text-decoration: none; padding-bottom: 1px;">Sign in</a>
                        @endif
                    @endauth
                </div>
            </nav>
        </header>

        <main style="flex: 1; display: flex; align-items: center; padding: 48px 20px;">
            <div style="max-width: 640px; margin: 0 auto; width: 100%; text-align: center;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 88px; height: 88px; border-radius: 24px; background: var(--paper-soft); margin-bottom: 28px;" aria-hidden="true">
                    @yield('icon')
                </div>

                <p class="font-mono" style="font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-soft); margin: 0 0 10px;">Error {{ $code }}</p>
                <h1 class="font-display" style="font-size: clamp(26px, 4vw, 34px); font-weight: 700; line-height: 1.2; margin: 0 0 14px;">@yield('title')</h1>
                <p style="font-size: 16px; line-height: 1.6; color: var(--ink-soft); margin: 0 0 32px;">@yield('message')</p>

                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 12px; margin-bottom: 56px;">
                    @yield('actions')
                </div>

                @if (!empty($discover))
                    <div style="border-top: 1px solid var(--line); padding-top: 28px; text-align: left;">
                        <p class="font-mono" style="font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink-soft); margin: 0 0 14px; text-align: center;">While you're here — the rest of the Dot Ecosystem ({{ count($discover) }})</p>
                        <div style="display: flex; gap: 8px; overflow-x: auto; padding: 2px 2px 8px; scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch;">
                            @foreach ($discover as $platform)
                                <a href="{{ $platform['url'] }}" class="press" style="flex: 0 0 auto; scroll-snap-align: start; display: flex; align-items: center; gap: 8px; padding: 7px 14px 7px 7px; background: #fff; border: 1px solid var(--line); border-radius: 999px; text-decoration: none; white-space: nowrap;">
                                    <span class="material-symbols-rounded" aria-hidden="true" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: {{ $platform['accent'] ?? 'var(--gold)' }}; color: #ffffff; font-size: 16px; flex-shrink: 0;">{{ $platform['icon'] ?? 'apps' }}</span>
                                    <span class="font-display" style="font-weight: 600; font-size: 13px; color: var(--ink);">{{ $platform['name'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </main>

        <footer style="background: var(--charcoal-soft); padding: 24px 20px;">
            <div style="max-width: 1400px; margin: 0 auto; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 16px;">
                <p class="font-mono" style="font-size: 12px; color: rgba(246,247,249,0.6); margin: 0;">&copy; {{ date('Y') }} InfoDot. The hub of the Dot Ecosystem.</p>
                @if (Route::has('contact'))
                    <a href="{{ route('contact') }}" class="link-underline font-mono" style="font-size: 12px; color: rgba(246,247,249,0.6); text-decoration: none; padding-bottom: 1px;">Contact support</a>
                @endif
            </div>
        </footer>
    </body>
</html>
