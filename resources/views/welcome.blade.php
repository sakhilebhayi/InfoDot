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
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --paper: #f6f7f9;
                --paper-soft: #eceef1;
                --ink: #16212c;
                --ink-soft: #4c5c6c;
                --blue: #2487d4;
                --blue-deep: #1a6bad;
                --silver: #bcbec0;
                --line: rgba(22, 33, 44, 0.11);
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
            }
            .link-underline {
                background-image: linear-gradient(currentColor, currentColor);
                background-position: 0 100%;
                background-repeat: no-repeat;
                transition: background-size 220ms var(--ease-out);
            }
        </style>
    </head>
    <body class="antialiased">

        <!-- Nav -->
        <header
            x-data="{ scrolled: false, mobileMenuOpen: false }"
            @scroll.window="scrolled = window.pageYOffset > 24"
            :class="scrolled ? 'bg-[#f6f7f9]/95 backdrop-blur-md border-b border-[var(--line)]' : 'border-b border-transparent'"
            class="fixed top-0 left-0 right-0 z-50 transition-colors duration-300"
        >
            <nav class="max-w-[1400px] mx-auto px-5 sm:px-8 py-3 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2.5 press">
                    <img src="{{ asset('img/logo.png') }}" alt="InfoDot" class="h-16 sm:h-20 w-auto">
                </a>

                <div class="hidden md:flex items-center gap-8 font-mono text-[13px] tracking-wide uppercase text-[var(--ink-soft)]">
                    <a href="#capabilities" class="link-underline hover:text-[var(--ink)] pb-0.5">Platform</a>
                    <a href="#features" class="link-underline hover:text-[var(--ink)] pb-0.5">Community</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="press flex items-center gap-2 px-5 py-2.5 bg-[var(--blue)] hover:bg-[var(--blue-deep)] text-white text-sm font-display font-semibold rounded-lg transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:block text-sm font-medium text-[var(--ink-soft)] hover:text-[var(--ink)] transition-colors">
                                Sign in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="press px-5 py-2.5 bg-[var(--blue)] hover:bg-[var(--blue-deep)] text-white text-sm font-display font-semibold rounded-lg transition-colors">
                                    Create account
                                </a>
                            @endif
                        @endauth

                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden press p-2 -mr-2 text-[var(--ink)]" aria-label="Toggle menu">
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
                 class="md:hidden border-t border-[var(--line)] bg-[#f6f7f9]"
                 style="display: none;">
                <div class="flex flex-col px-5 py-4 gap-1 font-mono text-sm uppercase tracking-wide">
                    <a href="#capabilities" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Platform</a>
                    <a href="#features" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Community</a>
                    @guest
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-[var(--ink-soft)] hover:text-[var(--ink)]">Sign in</a>
                    @endguest
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative min-h-[100dvh] flex items-end overflow-hidden">
            <!-- Photo: network/server cabling, already used as InfoDot's own hero image (credited to Taylor Vick, @tvick, unsplash.com/photos/cable-network-M5tzZtFCOfs) — kept because it maps directly onto InfoDot's real role as the connective layer between platforms -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(246,247,249,0.62) 0%, rgba(246,247,249,0.82) 45%, #f6f7f9 92%);"></div>
            <div class="absolute inset-0" style="background: linear-gradient(90deg, #f6f7f9 0%, rgba(246,247,249,0.62) 38%, transparent 68%);"></div>

            <!-- Signature mark — large line-art echo of the real logo's own icon geometry: an outer ring, an offset inner dot, and the forward chevron -->
            <svg class="hidden lg:block absolute right-[6%] bottom-[6%] h-[62%] w-auto opacity-[0.16] pointer-events-none" viewBox="0 0 280 220" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <circle cx="110" cy="110" r="92" stroke="#16212c" stroke-width="3"/>
                <circle cx="110" cy="150" r="10" fill="#2487d4"/>
                <path d="M230 60L280 110L230 160" stroke="#16212c" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>

            <div class="relative z-10 max-w-[1400px] mx-auto px-5 sm:px-8 pt-32 pb-16 sm:pb-20 w-full">
                <div class="max-w-2xl reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--blue)] mb-6">
                        Ecosystem hub &amp; knowledge base
                    </p>

                    <h1 class="font-display font-bold text-4xl sm:text-5xl lg:text-6xl leading-[1.05] tracking-tight text-[var(--ink)] mb-6">
                        One login.<br>Every Dot platform.
                    </h1>

                    <p class="text-lg text-[var(--ink-soft)] leading-relaxed max-w-xl mb-10">
                        InfoDot is the front door to the Dot Ecosystem — sign in once and move to any connected platform without logging in again. It's also where the community asks questions, shares Solutions, and keeps the conversation attached to the answer.
                    </p>

                    @guest
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('register') }}" class="press px-7 py-3.5 bg-[var(--blue)] hover:bg-[var(--blue-deep)] text-white font-display font-semibold rounded-lg transition-colors">
                                Create account
                            </a>
                            <a href="#features" class="press flex items-center gap-2 px-7 py-3.5 text-[var(--ink)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--silver)] transition-colors">
                                See what's inside
                            </a>
                        </div>
                    @endguest
                </div>
            </div>

            <!-- Live capability strip — a list of what's actually built, not a fabricated metric -->
            <div class="relative z-10 w-full border-t border-[var(--line)] bg-[#f6f7f9]/70 backdrop-blur-sm">
                <div class="max-w-[1400px] mx-auto px-5 sm:px-8 py-4 flex flex-wrap gap-x-8 gap-y-2 font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--ink-soft)]">
                    <span>Single sign-on</span>
                    <span class="text-[var(--blue)]">·</span>
                    <span>Q&amp;A knowledge base</span>
                    <span class="text-[var(--blue)]">·</span>
                    <span>Solutions &amp; how-to guides</span>
                    <span class="text-[var(--blue)]">·</span>
                    <span>Team workspaces</span>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="py-24 sm:py-28 px-5 sm:px-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="max-w-xl mb-16 reveal" data-reveal>
                    <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--blue)] mb-4">What it does</p>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight">
                        One account, and the community built on top of it
                    </h2>
                </div>

                <div class="grid md:grid-cols-2 border-t border-[var(--line)]">
                    @php
                        $features = [
                            ['tag' => 'SSO', 'title' => 'One login, every platform', 'body' => 'A short-lived, one-time handoff token moves you from InfoDot straight into any connected Dot platform — no separate password to remember or re-enter.'],
                            ['tag' => 'Questions', 'title' => 'Ask, and get a real answer', 'body' => 'Post a question, get answers from people who\'ve actually solved it, and browse what the rest of the ecosystem has already asked.'],
                            ['tag' => 'Solutions', 'title' => 'Solutions &amp; how-to guides', 'body' => 'Step-by-step Solutions built from ordered Steps, written by members for the problems they\'ve run into themselves.'],
                            ['tag' => 'Comments', 'title' => 'Threaded discussion on everything', 'body' => 'Every question and Solution carries its own comment thread, with likes on both, so context stays attached to the content.'],
                            ['tag' => 'Associates', 'title' => 'A social graph, not a follower count', 'body' => 'Associates connects you to the people you actually work with across the ecosystem — tracked in both directions, not just who you follow.'],
                            ['tag' => 'Teams', 'title' => 'Team workspaces', 'body' => 'Jetstream-powered teams with invitations, so an account on InfoDot isn\'t just one person signing in alone.'],
                        ];
                    @endphp
                    @foreach ($features as $i => $f)
                        <div class="row-hover border-b border-[var(--line)] {{ $i % 2 === 0 ? 'md:border-r' : '' }} px-1 py-8 sm:py-10 transition-colors reveal" data-reveal>
                            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--blue)] mb-3">{{ $f['tag'] }}</p>
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
                        <p class="font-mono text-xs tracking-[0.18em] uppercase text-[var(--blue)] mb-4">How it connects</p>
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
        <section class="relative py-28 sm:py-36 px-5 sm:px-8 overflow-hidden">
            <!-- Photo: business/community meeting, already an InfoDot brand asset used elsewhere in this app -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2400&auto=format&fit=crop');"></div>
            <div class="absolute inset-0" style="background: linear-gradient(180deg, #f6f7f9 0%, rgba(246,247,249,0.88) 50%, #f6f7f9 100%);"></div>

            <div class="relative z-10 max-w-2xl mx-auto text-center reveal" data-reveal>
                <h2 class="font-display font-semibold text-3xl sm:text-4xl text-[var(--ink)] leading-tight mb-5">
                    Every Dot platform starts at this login
                </h2>
                <p class="text-[var(--ink-soft)] leading-relaxed mb-10 max-w-lg mx-auto">
                    Create an account here and you're signed in everywhere the ecosystem connects — no separate registration on each platform.
                </p>

                @guest
                    <div class="flex flex-wrap justify-center gap-4">
                        <a href="{{ route('register') }}" class="press px-8 py-3.5 bg-[var(--blue)] hover:bg-[var(--blue-deep)] text-white font-display font-semibold rounded-lg transition-colors">
                            Create account
                        </a>
                        <a href="{{ route('login') }}" class="press px-8 py-3.5 text-[var(--ink)] font-medium rounded-lg border border-[var(--line)] hover:border-[var(--silver)] transition-colors">
                            Sign in
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-14 px-5 sm:px-8 border-t border-[var(--line)]">
            <div class="max-w-[1400px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="{{ asset('img/logo.png') }}" alt="InfoDot" class="h-11 w-auto">
                </a>

                <div class="flex items-center gap-6 font-mono text-xs tracking-wide uppercase text-[var(--ink-soft)]">
                    <a href="{{ route('about') }}" class="link-underline hover:text-[var(--ink)] pb-0.5">About</a>
                    <a href="{{ route('contact') }}" class="link-underline hover:text-[var(--ink)] pb-0.5">Contact</a>
                    <a href="{{ route('terms') }}" class="link-underline hover:text-[var(--ink)] pb-0.5">Terms</a>
                </div>

                <p class="font-mono text-xs tracking-wide text-[var(--ink-soft)]">
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
