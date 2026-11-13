{{-- The tab and home-screen icons, the same on every page (R-49). Every layout includes this and nothing else names an
     icon. The files sit at fixed addresses in public/ and are on the deploy's list of static files copied to the web
     root (.github/workflows/deploy.yml); they are rebuilt from the logo by tools/build-icons.py. The theme colour is
     the Yard's top bar (--ix-top). --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#1b2124">
