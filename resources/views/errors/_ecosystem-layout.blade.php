<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <title>{{ $code }} — @yield('title') · InfoDot</title>
        <meta name="robots" content="noindex">

        @include('partials.head-icons')

        {{-- An error page is drawn in The Yard like every other page: the top bar, a band, the status as a stencil
             numeral, the words, and the same buttons. Archivo, the stencil face and the icons are declared in the
             stylesheet and served from this site. --}}
        @php
            $viteManifest = public_path('build/manifest.json');

            // Every Dot platform, pulled from the shared ecosystem registry
            // (config/ecosystem.php, identical across all platforms) rather
            // than a fixed hand-picked subset -- add a platform to the
            // registry once and it shows up here automatically everywhere.
            $currentPlatformName = 'InfoDot';
            $discover = collect(\App\Support\Platforms::visible())
                ->reject(fn ($p) => ($p['name'] ?? null) === $currentPlatformName)
                ->reject(fn ($p) => ($p['active'] ?? true) === false)
                ->values()
                ->all();
        @endphp
        @if (file_exists($viteManifest))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            {{-- An error page must open even when the build is missing: the same layout in plain system type. --}}
            <style>
                body { margin: 0; font: 15px/1.55 system-ui, -apple-system, 'Segoe UI', sans-serif; background: #e3e6e7; color: #14181a; }
                a { color: inherit; }
                .ix-top, .ix-band { background: #1b2124; color: #f1f3f3; }
                .ix-top__inner, .ix-band__inner, .ix-page, .ix-foot { max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; justify-content: space-between; }
                .ix-page { display: block; padding-top: 48px; padding-bottom: 64px; }
                .ix-err__code { margin: 0; font-size: 96px; font-weight: 800; line-height: 1; }
                .ix-btn { display: inline-block; padding: 10px 16px; border: 1px solid #8c959a; border-radius: 2px; font-weight: 600; text-decoration: none; }
                .ix-btn--primary { background: #1b2124; border-color: #1b2124; color: #f6f4ee; }
                .ix-err__actions, .ix-err__more ul { display: flex; flex-wrap: wrap; gap: 10px 16px; padding: 0; list-style: none; }
                @media (prefers-color-scheme: dark) { body { background: #111517; color: #e9ecec; } .ix-btn--primary { background: #e9ecec; border-color: #e9ecec; color: #14181a; } }
            </style>
        @endif
    </head>
    <body class="ix" data-area="home">
        <div class="ix-shell">
            <header class="ix-top ix-top--public">
                <div class="ix-top__inner">
                    <a href="/" class="ix-brand" aria-label="InfoDot home">
                        {{-- The logo falls back to the name when the build does not hold it. --}}
                        @if ($logo = \App\Support\OptionalVite::asset('resources/img/brand/logo_white.png'))
                            <img src="{{ $logo }}" alt="InfoDot" width="64" height="26">
                        @else
                            <strong>InfoDot</strong>
                        @endif
                    </a>
                    <div class="ix-top__actions">
                        @auth
                            <a href="{{ route('dashboard') }}" class="ix-btn">Open InfoDot</a>
                        @else
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="ix-btn">Sign in</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </header>

            <div class="ix-band">
                <div class="ix-band__inner">
                    <span class="ix-band__code">STOP</span>
                    <nav class="ix-crumbs" aria-label="Breadcrumb">
                        <a href="/">InfoDot</a>
                        <span class="material-symbols-rounded sep" aria-hidden="true">chevron_right</span>
                        <span class="here" aria-current="page">Error {{ $code }}</span>
                    </nav>
                </div>
            </div>

            <main id="main" class="ix-page ix-err">
                <p class="ix-stencil-num ix-err__code" aria-hidden="true">{{ $code }}</p>

                <div class="ix-err__body">
                    <h1 class="ix-h1">@yield('title')</h1>
                    <p class="ix-lede">@yield('message')</p>

                    @hasSection('reference')
                        {{-- Something to quote to support: the id on the matching log line. Selects whole on one tap; a long one wraps. --}}
                        <p class="ix-err__ref">Reference: <span class="ix-ref">@yield('reference')</span></p>
                    @endif

                    <div class="ix-err__actions">
                        @yield('actions')
                    </div>
                </div>

                @if (!empty($discover))
                    <section class="ix-err__more" aria-labelledby="err-more">
                        <h2 id="err-more">While you're here: the rest of the Dot Ecosystem ({{ count($discover) }})</h2>
                        <ul>
                            @foreach ($discover as $platform)
                                <li><a href="{{ $platform['url'] }}">{{ $platform['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </main>

            <footer class="ix-foot">
                <span>&copy; {{ date('Y') }} InfoDot. The hub of the Dot Ecosystem.</span>
                @if (Route::has('contact'))
                    <nav aria-label="Help"><a href="{{ route('contact') }}">Contact support</a></nav>
                @endif
            </footer>
        </div>
    </body>
</html>
