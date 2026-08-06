<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>InfoDot — The hub of the Dot Ecosystem</title>
        <meta name="description" content="InfoDot is the central identity provider for the Dot Ecosystem — sign in once and move between every connected platform. It also carries a public Q&amp;A hub, a Solutions how-to library, and team workspaces.">

        <link rel="icon" href="{{ asset('favicon.ico') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
            html { background: var(--paper); }
            body { font-family: var(--font-body); background: var(--paper); color: var(--ink); }
            .font-display { font-family: var(--font-display); }
            .font-mono { font-family: var(--font-mono); }

            .press { transition: transform 160ms var(--ease-out); }
            .press:active { transform: scale(0.97); }

            @media (prefers-reduced-motion: no-preference) {
                .reveal {
                    opacity: 0;
                    transform: translateY(14px);
                    transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out);
                }
                .reveal.is-visible { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; }
            }

            @media (hover: hover) and (pointer: fine) {
                .row-hover:hover { background: rgba(22, 33, 44, 0.025); }
                .link-underline { background-size: 0% 1px; }
                .link-underline:hover { background-size: 100% 1px; }
                .lift-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -16px rgba(22,33,44,0.22); }
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
            .lift-hover { transition: transform 220ms var(--ease-out), box-shadow 220ms var(--ease-out); }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav — persistently dark, matching the live infodot.co.za header, so the white-on-gold lockup always has the contrast it needs -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-[rgba(36,39,43,0.9)] backdrop-blur-md" x-data="{ mobileMenuOpen: false }">
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot" class="h-12 sm:h-14 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[rgba(246,247,249,0.7)]">
                    <a href="#capabilities" class="link-underline hover:text-[var(--paper)] pb-0.5">Platform</a>
                    <a href="#features" class="link-underline hover:text-[var(--paper)] pb-0.5">Community</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-deep)] text-[var(--charcoal)] text-sm font-display font-semibold rounded-full transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[rgba(246,247,249,0.8)] hover:text-[var(--paper)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-deep)] text-[var(--charcoal)] text-sm font-display font-semibold rounded-full transition-colors">
                                    Create account
                                </a>
                            @endif
                        @endauth

                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden press p-2 -mr-2 text-[var(--paper)]" aria-label="Toggle menu">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16"></path>
                                <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </nav>

            <div x-show="mobileMenuOpen"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="md:hidden border-t border-[var(--line-dark)] bg-[var(--charcoal)]"
                 style="display: none;">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#capabilities" class="px-3 py-2.5 text-[rgba(246,247,249,0.7)] hover:text-[var(--paper)]">Platform</a>
                    <a href="#features" class="px-3 py-2.5 text-[rgba(246,247,249,0.7)] hover:text-[var(--paper)]">Community</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[rgba(246,247,249,0.7)] hover:text-[var(--paper)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero — full-bleed dark photo, oversized centered logo lockup, rounded-pill CTAs: the live site's own layout language -->
        <section class="relative min-h-[92dvh] flex flex-col items-center justify-center overflow-hidden pt-16">
            <!-- Photo: network/server cabling — InfoDot's existing hero image, credited to Taylor Vick, @tvick, unsplash.com/photos/cable-network-M5tzZtFCOfs — kept because it maps directly onto InfoDot's role as the connective layer between platforms -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: radial-gradient(ellipse 68% 62% at 50% 40%, rgba(36,39,43,0.88) 0%, rgba(36,39,43,0.62) 45%, rgba(36,39,43,0.3) 74%, rgba(36,39,43,0.1) 100%);"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(36,39,43,0.55) 0%, transparent 18%, transparent 74%, rgba(36,39,43,0.45) 100%);"></div>

            <div class="relative z-10 max-w-3xl mx-auto px-5 sm:px-8 py-16 sm:py-20 w-full flex flex-col items-center text-center reveal" data-reveal>
                <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot" class="h-24 sm:h-32 w-auto mb-8">

                <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold)] mb-5" style="text-shadow: 0 2px 8px rgba(0,0,0,0.6);">
                    Ecosystem hub &amp; knowledge base
                </p>

                <h1 class="font-display font-bold text-3xl sm:text-4xl leading-[1.15] tracking-tight text-[var(--paper)] mb-6 max-w-xl">
                    One login. Every Dot platform.
                </h1>

                <p class="text-base sm:text-lg text-[rgba(246,247,249,0.7)] leading-relaxed max-w-lg mb-8">
                    Sign in once and move to any connected platform without logging in again. It's also where the community asks questions, shares Solutions, and keeps the conversation attached to the answer.
                </p>

                <!-- Real search, not decorative: posts to the same solution_search_results route (App\Http\Controllers\PagesController::solution_search_results) that already powers search across Questions and Solutions elsewhere in this app -->
                <form action="{{ route('solution_search_results') }}" method="GET" class="w-full max-w-lg mb-8">
                    <div class="relative">
                        <input
                            type="text"
                            name="search"
                            placeholder="Ask: how do I connect a new platform?"
                            class="w-full pl-6 pr-14 py-4 rounded-full bg-[var(--paper)] text-[var(--ink)] placeholder-[var(--ink-soft)] font-body text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-[var(--gold)] shadow-[0_16px_40px_-16px_rgba(0,0,0,0.5)]"
                        >
                        <button type="submit" aria-label="Search Questions and Solutions" class="press absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-[var(--gold)] hover:bg-[var(--gold-deep)] flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4 text-[var(--charcoal)]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="7"/>
                                <path d="M21 21l-4.35-4.35" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </form>

                @guest
                    <div class="flex flex-wrap items-center justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-deep)] text-[var(--charcoal)] font-display font-semibold rounded-full transition-colors">
                            Create account
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 bg-white/10 hover:bg-white/15 text-[var(--paper)] font-display font-medium rounded-full border border-[var(--line-dark)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Feature cards — overlapping up into the hero, matching the live site's raised-card treatment; badges describe InfoDot's real capabilities -->
        <section class="relative z-20 -mt-16 sm:-mt-20 px-5 sm:px-8">
            <div class="max-w-[1200px] mx-auto grid sm:grid-cols-3 gap-6">
                @php
                    $cards = [
                        ['color' => 'var(--coral)', 'title' => 'Single Sign-On', 'body' => 'One account, a one-time handoff token, and every connected Dot platform is already signed in.', 'icon' => '<path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v2" stroke-linecap="round" stroke-linejoin="round"/>'],
                        ['color' => 'var(--blue-dot)', 'title' => 'Ecosystem Directory', 'body' => 'Every platform listed is a verified, first-party Dot app — one launcher away, no bookmarks or separate logins to track.', 'icon' => '<path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>'],
                        ['color' => 'var(--green)', 'title' => 'Community Q&A', 'body' => 'Ask a real question, get answered by people who\'ve solved it, and browse Solutions the community has already written.', 'icon' => '<path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l1.3-3.9A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke-linecap="round" stroke-linejoin="round"/>'],
                    ];
                @endphp
                @foreach ($cards as $c)
                    <div class="lift-hover bg-white rounded-2xl shadow-[0_16px_40px_-20px_rgba(22,33,44,0.35)] p-7 sm:p-8 reveal" data-reveal>
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-5" style="background: {{ $c['color'] }};">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">{!! $c['icon'] !!}</svg>
                        </div>
                        <h3 class="font-display font-semibold text-lg text-[var(--ink)] mb-2">{{ $c['title'] }}</h3>
                        <p class="text-sm text-[var(--ink-soft)] leading-relaxed">{{ $c['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Story section — icon + heading + copy alongside a photo card with a color-block overlay, echoing the live site's two-column layout -->
        <section class="pt-24 sm:pt-28 pb-16 px-5 sm:px-8 bg-[var(--paper)]">
            <div class="max-w-[1200px] mx-auto grid lg:grid-cols-2 gap-14 lg:gap-20 items-center">
                <div class="reveal" data-reveal>
                    <div class="w-14 h-14 rounded-full bg-white shadow-[0_8px_24px_-8px_rgba(22,33,44,0.25)] flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-[var(--gold-deep)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 10-8 0" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight mb-5">
                        We're the front door, not another silo
                    </h2>
                    <p class="text-[var(--ink-soft)] leading-relaxed mb-4 max-w-md">
                        InfoDot is the central identity provider for the Dot Ecosystem. Every connected platform points at the same shared PostgreSQL instance and trusts the identity InfoDot issues — sign in here once, and the handoff to any of them is instant.
                    </p>
                    <p class="text-[var(--ink-soft)] leading-relaxed max-w-md">
                        It's also its own community: public Questions and Solutions, threaded comments and likes on both, and Associates — a social graph tracked in both directions, not just a follower count.
                    </p>
                </div>

                <div class="relative reveal" data-reveal>
                    <div class="rounded-2xl overflow-hidden shadow-[0_24px_60px_-24px_rgba(22,33,44,0.4)]">
                        <!-- Photo: business/community meeting, already an InfoDot brand asset used elsewhere in this app -->
                        <div class="aspect-[4/3] bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1600&auto=format&fit=crop');"></div>
                        <div class="p-7 sm:p-8" style="background: var(--gold);">
                            <h3 class="font-display font-semibold text-xl text-[var(--charcoal)] mb-2">
                                Team workspaces from day one
                            </h3>
                            <p class="text-sm text-[rgba(36,39,43,0.8)] leading-relaxed">
                                Jetstream Teams with invitations, so an InfoDot account isn't just one person signing in alone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold-deep)] mb-4">What it does</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight">
                        One account, and the community built on top of it
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'SSO', 'title' => 'One login, every platform', 'body' => 'A short-lived, one-time handoff token moves you from InfoDot straight into any connected Dot platform — no separate password to remember or re-enter.'],
                            ['tag' => 'Questions', 'title' => 'Ask, and get a real answer', 'body' => 'Post a question, get answers from people who\'ve actually solved it, and browse what the rest of the ecosystem has already asked.'],
                            ['tag' => 'Solutions', 'title' => 'Solutions & how-to guides', 'body' => 'Step-by-step Solutions built from ordered Steps, written by members for the problems they\'ve run into themselves.'],
                            ['tag' => 'Comments', 'title' => 'Threaded discussion on everything', 'body' => 'Every question and Solution carries its own comment thread, with likes on both, so context stays attached to the content.'],
                            ['tag' => 'Associates', 'title' => 'A social graph, not a follower count', 'body' => 'Associates connects you to the people you actually work with across the ecosystem — tracked in both directions, not just who you follow.'],
                            ['tag' => 'Teams', 'title' => 'Team workspaces', 'body' => 'Jetstream-powered teams with invitations, so an account on InfoDot isn\'t just one person signing in alone.'],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--gold-deep)] mb-3">{{ $f['tag'] }}</p>
                            <h3 class="font-display font-semibold text-xl text-[var(--ink)] mb-2.5">{{ $f['title'] }}</h3>
                            <p class="text-[var(--ink-soft)] leading-relaxed max-w-md">{{ $f['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Capabilities -->
        <section id="capabilities" class="py-24 sm:py-28 px-5 sm:px-8 bg-[var(--paper-soft)] border-y border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-12 lg:gap-20">
                    <div class="reveal" data-reveal>
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--gold-deep)] mb-4">How it connects</p>
                        <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight mb-5">
                            One account, one shared database, every Dot platform
                        </h2>
                        <p class="text-[var(--ink-soft)] leading-relaxed max-w-sm">
                            Every platform in the Dot Ecosystem points at the same PostgreSQL instance and trusts the identity InfoDot issues. Sign in here once, and the handoff to any connected platform is instant.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-10">
                        @php
                            $capabilities = [
                                ['title' => 'Livewire, real time', 'body' => 'Interface updates without full page reloads, built on Livewire 3 components.'],
                                ['title' => 'One-time handoff tokens', 'body' => 'Each SSO token is single-use, expires in five minutes, and is scoped to exactly one ability — issued by Sanctum, not a shared session cookie.'],
                                ['title' => 'Full-text search', 'body' => 'Solutions and comments are indexed with Laravel Scout, so answers surface by more than an exact keyword match.'],
                                ['title' => 'Public by design', 'body' => 'Questions and Solutions are shared across the whole community — not walled off per account or per team.'],
                                ['title' => 'Team invitations', 'body' => 'Jetstream Teams with invitations, so a workspace can hold more than one person from day one.'],
                                ['title' => 'Shared PostgreSQL schema', 'body' => 'The same users, teams, and tokens tables that every connected platform reads from directly.'],
                            ];
                        @endphp
                        @foreach ($capabilities as $c)
                            <div class="py-6 border-t border-[var(--line)] reveal" data-reveal>
                                <h3 class="font-display font-medium text-base text-[var(--ink)] mb-1.5">{{ $c['title'] }}</h3>
                                <p class="text-sm text-[var(--ink-soft)] leading-relaxed">{{ $c['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden bg-[var(--charcoal)]">
            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot" class="h-16 w-auto mx-auto mb-8">
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--paper)] leading-tight mb-5">
                    Every Dot platform starts at this login
                </h2>
                <p class="text-[rgba(246,247,249,0.7)] leading-relaxed mb-10 max-w-lg mx-auto">
                    Create an account here and you're signed in everywhere the ecosystem connects — no separate registration on each platform.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--gold)] hover:bg-[var(--gold-deep)] text-[var(--charcoal)] font-display font-semibold rounded-full transition-colors">
                            Create account
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 bg-white/10 hover:bg-white/15 text-[var(--paper)] font-display font-medium rounded-full border border-[var(--line-dark)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 bg-[var(--charcoal-soft)]">
            <div class="max-w-[1400px] mx-auto flex flex-col gap-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                    <a href="/" class="flex items-center gap-2.5">
                        <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot" class="h-9 w-auto">
                    </a>

                    <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 font-mono text-xs tracking-wide uppercase text-[rgba(246,247,249,0.6)]">
                        <a href="{{ route('about') }}" class="link-underline hover:text-[var(--paper)] pb-0.5">About</a>
                        <a href="{{ route('features') }}" class="link-underline hover:text-[var(--paper)] pb-0.5">Features</a>
                        <a href="{{ route('contact') }}" class="link-underline hover:text-[var(--paper)] pb-0.5">Contact</a>
                        <a href="{{ route('policy') }}" class="link-underline hover:text-[var(--paper)] pb-0.5">Privacy</a>
                        <a href="{{ route('cookies') }}" class="link-underline hover:text-[var(--paper)] pb-0.5">Cookies</a>
                        <a href="{{ route('terms') }}" class="link-underline hover:text-[var(--paper)] pb-0.5">Terms</a>
                    </div>

                    <div class="flex items-center gap-4">
                        <a href="https://web.facebook.com/infodotbusinesses" target="_blank" rel="noopener noreferrer" aria-label="InfoDot on Facebook" class="text-[rgba(246,247,249,0.6)] hover:text-[var(--gold)] transition-colors">
                            <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.89h2.78l-.44 2.91h-2.34V22c4.78-.76 8.44-4.92 8.44-9.94z"/></svg>
                        </a>
                        <a href="https://twitter.com/InfoDot3" target="_blank" rel="noopener noreferrer" aria-label="InfoDot on X" class="text-[rgba(246,247,249,0.6)] hover:text-[var(--gold)] transition-colors">
                            <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24"><path d="M18.9 2h3.3l-7.2 8.2L23.4 22h-6.6l-5.2-6.8L5.6 22H2.3l7.7-8.8L1.6 2h6.8l4.7 6.2zm-1.2 18h1.8L7.4 3.9H5.5z"/></svg>
                        </a>
                        <a href="https://www.instagram.com/infodot_businesses_official/" target="_blank" rel="noopener noreferrer" aria-label="InfoDot on Instagram" class="text-[rgba(246,247,249,0.6)] hover:text-[var(--gold)] transition-colors">
                            <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.25 1.21.6 1.76 1.15.5.5.9 1.1 1.15 1.76.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.13s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 01-1.15 1.76 4.9 4.9 0 01-1.76 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.13-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 01-1.76-1.15 4.9 4.9 0 01-1.15-1.76c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.25-.66.6-1.21 1.15-1.76a4.9 4.9 0 011.76-1.15c.64-.25 1.37-.42 2.43-.47C8.94 2.01 9.28 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm0 8.25A3.25 3.25 0 1112 8.75a3.25 3.25 0 010 6.5zm5.2-8.7a1.17 1.17 0 100-2.34 1.17 1.17 0 000 2.34z"/></svg>
                        </a>
                        <a href="https://www.linkedin.com/company/infodot/" target="_blank" rel="noopener noreferrer" aria-label="InfoDot on LinkedIn" class="text-[rgba(246,247,249,0.6)] hover:text-[var(--gold)] transition-colors">
                            <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.03-1.85-3.03-1.86 0-2.15 1.45-2.15 2.94v5.66H9.35V9h3.41v1.56h.05c.47-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29zM5.34 7.43a2.06 2.06 0 110-4.12 2.06 2.06 0 010 4.12zM7.12 20.45H3.56V9h3.56z"/></svg>
                        </a>
                    </div>
                </div>

                <p class="font-mono text-xs tracking-wide text-[rgba(246,247,249,0.6)] text-center sm:text-left">
                    &copy; {{ date('Y') }} InfoDot. The hub of the Dot Ecosystem.
                </p>
            </div>
        </footer>

        <script>
            if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach((el) => io.observe(el));
            } else {
                document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
            }
        </script>
    </body>
</html>
