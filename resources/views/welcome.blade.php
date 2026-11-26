@php
    // Every live platform that has a logo of its own, in the config's order. The files are the platforms' logos trimmed to their artwork, so they all fill their tile alike.
    $platforms = collect(config('ecosystem.platforms', []))
        // Only logos that carry the platform's name: the Dot.Agents mark has no wording, so it is left out of the wall.
        ->reject(fn ($p, $key) => $key === 'agents')
        ->filter(fn ($p, $key) => ($p['active'] ?? false) && ! ($p['internal'] ?? false) && $key !== 'infodot' && file_exists(resource_path('img/landing/platforms/'.$key.'.png')))
        ->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name'], 'logo' => \Illuminate\Support\Facades\Vite::asset('resources/img/landing/platforms/'.$key.'.png')])->values();
    // The tools, grouped by what a person wants to get done. Only platforms that are live, public and have their own logo appear;
    // a group with none of its platforms live disappears. Which of them a given business may open is decided after sign-in.
    $tool = $platforms->keyBy('key');
    $groups = collect([
        ['title' => 'Understand your business', 'keys' => ['analytics', 'charts', 'pulse']],
        ['title' => 'Manage your money', 'keys' => ['finance']],
        ['title' => 'Manage your people', 'keys' => ['hr']],
        ['title' => 'Organise your work', 'keys' => ['projects', 'tasks']],
        ['title' => 'Create and collaborate', 'keys' => ['design', 'docs', 'sheet', 'forms', 'press']],
        ['title' => 'Manage information', 'keys' => ['files', 'memory']],
        ['title' => 'Communicate and connect', 'keys' => ['notify', 'engage']],
        ['title' => 'Access specialised solutions', 'keys' => ['mines', 'auction', 'ehail', 'emall', 'tutor']],
    ])->map(fn ($g) => ['title' => $g['title'], 'tools' => collect($g['keys'])->map(fn ($k) => $tool->get($k))->filter()->values()])
      ->filter(fn ($g) => $g['tools']->isNotEmpty())->values();
    $examples = ['How do I register a business?', 'How do I price my work?', 'How do I find customers?', 'How do I track my cash?', 'How do I hire my first person?'];
    $search = fn (string $words) => route('solution_search_results', ['search' => $words]);
    $tools = route('business-tools');
    $first = auth()->check() ? \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')->toString() : null;
    // The researched library: public solutions published by the official InfoDot account (R-14/R-23).
    // Real rows from the database — the section disappears entirely when none exist yet.
    $library = \App\Models\Solutions::withoutGlobalScopes()->whereNotNull('seed_key')
        ->where('visibility', \App\Models\Solutions::VISIBILITY_PUBLIC)->whereNull('hidden_at')
        ->with('topics')->orderByDesc('id')->limit(6)->get();
    $libraryCount = $library->count() < 6 ? $library->count()
        : \App\Models\Solutions::withoutGlobalScopes()->whereNotNull('seed_key')->where('visibility', \App\Models\Solutions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->count();
@endphp
<x-public-layout landing title="InfoDot" description="Most businesses are figuring it out as they go. InfoDot brings business knowledge, connected tools and other businesses into one free account, so you can start, manage and grow with more to go on.">
    {{-- Who publishes this site, for search engines (R-17 §13.3). Facts only: name, address, contact page. --}}
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'Organization', 'name' => 'InfoDot', 'url' => route('welcome'), 'logo' => asset('icon-512.png')],
            ['@type' => 'WebSite', 'name' => 'InfoDot', 'url' => route('welcome')],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- 1. The hero says what InfoDot is and what to do next. The search stays. The picture side moves through three compositions; the words never move. --}}
    @include('partials.home-hero')

    {{-- 2. The reality (R-67). The owner's own heading: provocative on purpose. The list and the turn below it stand with the
         reader: ambition is not the problem, access is. No statistics; every line is a situation an owner will recognise. --}}
    <section class="hp-reality" aria-labelledby="reality-title">
        <div class="hp-reality__head">
            <h2 id="reality-title" class="hp-reality__title" data-reveal>The truth is, most businesses don't really know what they're doing. <span class="hp-reality__turn">InfoDot is here to change that.</span></h2>
            <p class="lp-lede" data-reveal>Nobody hands you a manual when you start a business. So most of us learn the expensive way, one decision at a time. If you run a business, some of this will sound familiar.</p>
        </div>
        <ul class="hp-reality__list" role="list">
            <li data-reveal>You make big decisions without knowing who to ask, or whether the advice you found is any good.</li>
            <li data-reveal>The business runs on spreadsheets, WhatsApp threads, email and a phone full of files.</li>
            <li data-reveal>You fix the same problem twice, because nobody wrote down how it was solved the first time.</li>
            <li data-reveal>You know there must be a better tool for the job. You just don't know which one.</li>
            <li data-reveal>The supplier, partner or customer you need is out there. You can't see them from where you stand.</li>
            <li data-reveal>You want to grow, but nobody can tell you what the next step is.</li>
        </ul>
        <p class="hp-reality__close" data-reveal><strong>None of this is a lack of ambition. It's a lack of access.</strong> To knowledge, to the right tools and to the people who could help. That is the gap InfoDot fills.</p>
    </section>

    {{-- 3. The answer: four things every business needs, each a tension and what InfoDot gives instead. Every promise is something that works today. --}}
    <section class="hp-pillars hp-pillars--four" id="pillars" aria-labelledby="pillars-title">
        <div class="hp-pillars__intro">
            <h2 id="pillars-title" data-reveal>Four things every business needs. One place to find them.</h2>
            <p class="lp-lede" data-reveal>InfoDot brings knowledge, tools and other businesses together, and helps you act on what you find. Start with whichever you need today.</p>
        </div>

        <div class="hp-pillars__list">
            <article class="hp-pillar hp-pillar--discover" id="knowledge" data-pillar aria-labelledby="knowledge-title">
                <h3 id="knowledge-title" class="hp-pillar__plate" style="--pillar-img: url('{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/market-jars-800.jpg') }}')"><span class="hp-pillar__word">Knowledge</span><span class="hp-pillar__tag">Stop figuring it out alone</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>Ask in plain words and hear from people who have been there. Follow researched solutions, step by step, with their sources named and dated.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Ask a question; other businesses answer.</li>
                        <li>See which answer solved it, and which have been checked.</li>
                        <li>Follow researched steps on tax, hiring, pricing and more.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal><a href="{{ route('solution_search_results') }}" class="ix-btn lp-btn">Search business solutions</a><a href="{{ route('seek') }}" class="hp-textlink">Ask a question</a></p>
                </div>
            </article>

            <article class="hp-pillar hp-pillar--manage" id="tools-pillar" data-pillar aria-labelledby="tools-pillar-title">
                <h3 id="tools-pillar-title" class="hp-pillar__plate" style="--pillar-img: url('{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/market-burger-800.jpg') }}')"><span class="hp-pillar__word">Tools</span><span class="hp-pillar__tag">Stop juggling disconnected apps</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>One InfoDot account signs you in to the Dot tools your business uses, built to work together instead of living in separate corners.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Keep your books and send invoices.</li>
                        <li>Look after your people and their records.</li>
                        <li>Plan projects, share tasks, keep documents and files.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal><a href="#tools" class="ix-btn lp-btn">See the tools by goal</a></p>
                </div>
            </article>

            <article class="hp-pillar hp-pillar--connect" id="connections" data-pillar aria-labelledby="connections-title">
                <h3 id="connections-title" class="hp-pillar__plate" style="--pillar-img: url('{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/bokaap-street-800.jpg') }}')"><span class="hp-pillar__word">Connections</span><span class="hp-pillar__tag">Stop missing what you can't see</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>Find businesses by what they do and where they work. Message them as your company, or ask for an appointment, straight from their page.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Give your business a page people can find.</li>
                        <li>Search the directory by need and place.</li>
                        <li>Message, book or request an appointment.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal><a href="{{ route('businesses.index') }}" class="ix-btn lp-btn">Browse businesses</a></p>
                </div>
            </article>

            <article class="hp-pillar hp-pillar--grow" id="progress" data-pillar aria-labelledby="progress-title">
                <h3 id="progress-title" class="hp-pillar__plate" style="--pillar-img: url('{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/kalkbay-boats-800.jpg') }}')"><span class="hp-pillar__word">Progress</span><span class="hp-pillar__tag">Turn what you learn into what you do</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>Knowledge only counts once it changes how you work. InfoDot keeps what your business learns, so the next person starts from the answer, not from zero.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Keep your company's questions and solutions together.</li>
                        <li>Improve a solution over time; earlier versions are kept.</li>
                        <li>Add people and tools as the business grows.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal>@guest<a href="{{ route('register') }}" class="ix-btn lp-btn">Start for free</a>@else<a href="{{ route('seek') }}" class="ix-btn lp-btn">Ask a question</a>@endguest</p>
                </div>
            </article>
        </div>
    </section>

    {{-- 4. The possibilities, made ordinary: five days, each using something InfoDot does today. The line fills as the visitor scrolls. --}}
    <section class="hp-how hp-week" id="how-it-works" aria-labelledby="how-title">
        <div class="hp-how__head">
            <h2 id="how-title" data-reveal>What a week with InfoDot can look like.</h2>
            <p class="lp-lede" data-reveal>Five ordinary days. Each one uses something InfoDot does today.</p>
        </div>
        <ol class="hp-steps hp-steps--week" role="list" data-journey>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">Mon</span>
                <h3><span class="ix-sr">Monday: </span>Sort out tax.</h3>
                <p>You search "SARS", open a researched solution and work through its steps, with the sources beside them.</p>
            </li>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">Tue</span>
                <h3><span class="ix-sr">Tuesday: </span>Send an invoice.</h3>
                <p>You open Dot.Finance with the same InfoDot sign-in and bill the customer you finished for last week.</p>
            </li>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">Wed</span>
                <h3><span class="ix-sr">Wednesday: </span>Find a supplier.</h3>
                <p>You need a printer for a launch. You find one in the directory and ask for an appointment from their page.</p>
            </li>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">Thu</span>
                <h3><span class="ix-sr">Thursday: </span>Bring someone in.</h3>
                <p>A new person starts. You add them to your company, and they sign in with a login of their own.</p>
            </li>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">Fri</span>
                <h3><span class="ix-sr">Friday: </span>Write it down.</h3>
                <p>A customer's question stumped you this week. You record how you solved it, so nobody solves it twice.</p>
            </li>
        </ol>
        @guest
            <p class="hp-how__cta" data-reveal><a href="{{ route('register') }}" class="ix-btn ix-btn--primary lp-btn">Join InfoDot for free</a></p>
        @endguest
    </section>

    {{-- 4. The tools, by what you want to get done. Names are there for recognition, not as a directory to read. --}}
    <section class="hp-tools" id="tools" aria-labelledby="tools-title">
        <div class="hp-tools__head">
            <h2 id="tools-title" data-reveal>Your business. Your tools. One connected ecosystem.</h2>
            <p class="lp-lede" data-reveal>Grouped by what you want to get done, and opened with your one InfoDot account. Use the ones your business needs and add more when it needs them.</p>
            <p class="hp-tools__note" data-reveal>Which tools you can open depends on your business and how access is arranged; not every tool comes with every account.</p>
            <p data-reveal><a href="{{ $tools }}" class="ix-btn ix-btn--primary lp-btn">Explore Business Tools</a></p>
        </div>
        <div class="hp-tools__groups">
            @foreach ($groups as $group)
                <div class="hp-group" data-group>
                    <h3 data-reveal>{{ $group['title'] }}</h3>
                    <ul class="lp-logos" aria-label="{{ $group['title'] }}">
                        @foreach ($group['tools'] as $platform)
                            <li data-logo><img src="{{ $platform['logo'] }}" alt="{{ $platform['name'] }}" loading="lazy" decoding="async"></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 5. The proof: how a question becomes something a business can rely on, and the researched library. --}}
    <section class="hp-chain" id="how-knowledge-grows" aria-labelledby="chain-title">
        <div class="hp-chain__head">
            <h2 id="chain-title" data-reveal>How a question becomes knowledge</h2>
            <p class="lp-lede" data-reveal>Not everything on InfoDot carries the same weight, and we show you which is which, so you know how far to rely on it.</p>
        </div>
        <ol class="hp-chain__steps" role="list" data-chain>
            <li data-station>
                <h3>Asked</h3>
                <p>Someone describes a problem in plain words.</p>
            </li>
            <li data-station>
                <h3>Answered</h3>
                <p>People who know reply. Questions stay with your company unless someone chooses to make them public.</p>
            </li>
            <li data-station>
                <h3>Accepted</h3>
                <p>The person who asked picks the answer that actually solved it, and it is stamped.</p>
                <span class="ix-stamp hp-chain__stamp" data-stamp aria-hidden="true">Solved</span>
            </li>
            <li data-station>
                <h3>Verified</h3>
                <p>Where a moderator who did not write it has checked a solution, it is marked verified.</p>
            </li>
        </ol>
        <p class="hp-chain__note" data-reveal>A community answer is not the same as an accepted solution, and an accepted solution is not the same as verified information. Nothing is shown as verified unless it has been checked.</p>
    </section>

    @if ($library->isNotEmpty())
        <section class="ix-pubsec" aria-labelledby="library-title">
            <div class="lp-library__head">
                <div>
                    <h2 id="library-title" data-reveal>A library you can act on</h2>
                    <p class="ix-pubsec__lede" data-reveal>Researched business solutions, published under InfoDot's official account: numbered steps you can follow, written from sources that are named and dated — SARS, National Treasury, the Information Regulator, and the people who study each subject. Where a figure changes yearly, the entry says so and shows when it was last verified.</p>
                </div>
                <p class="lp-library__tally" data-reveal><span class="ix-stencil-num lp-library__num">{{ $libraryCount }}</span><span class="lp-library__word">researched<br>entries</span></p>
            </div>
            <ol class="lp-ledger" data-reveal>
                @foreach ($library as $entry)
                    <li>
                        <a href="{{ route('solutions.view', $entry->id) }}">
                            <span class="ix-ref lp-ledger__ref">{{ \App\Support\Reference::for($entry) }}</span>
                            <span class="lp-ledger__body">
                                <strong>{{ $entry->solution_title }}</strong>
                                <span class="lp-ledger__meta">{{ $entry->steps }} steps · about {{ $entry->duration }} {{ $entry->duration == 1 ? \Illuminate\Support\Str::singular($entry->duration_type) : $entry->duration_type }}@if ($entry->topics->isNotEmpty()) · {{ $entry->topics->pluck('name')->join(', ') }}@endif</span>
                            </span>
                            <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <div class="lp-library__foot" data-reveal>
                <a href="{{ route('solutions') }}" class="ix-btn lp-btn">Browse all solutions</a>
                <p class="lp-library__note">Reading one asks you to sign in first — the account is free, and you come straight back to the entry.</p>
            </div>
        </section>
    @endif

    {{-- 6. Who it is for: every stage, no experience needed. --}}
    <section class="lp-split hp-who" aria-labelledby="who-title">
        <figure class="lp-photo" data-reveal>
            <div class="lp-photo__frame" data-parallax>
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/market-vegetables-1200.jpg') }}" width="1200" height="800" alt="A vegetable stall at a Cape Town market: tomatoes, avocados, fennel, radishes and herbs in boxes" loading="lazy" decoding="async">
            </div>
        </figure>
        <div class="lp-split__body">
            <h2 id="who-title" data-reveal>Wherever you are, there's a next step.</h2>
            <p class="lp-lede" data-reveal>You don't need a company, a plan or any experience to join. Start from where your business is today.</p>
            <ol class="hp-who__list" role="list" data-journey data-vertical>
                <li data-stage><a href="{{ route('solution_search_results', ['topic' => 'getting-started']) }}"><strong>You have a business idea and don't know where to start</strong><span>Read the first steps other people wrote down.</span></a></li>
                <li data-stage><a href="{{ $search('register') }}"><strong>You're starting your first business</strong><span>Registration, pricing and your first customers, in the words of people who did it.</span></a></li>
                <li data-stage><a href="#tools"><strong>You run a small business and want an affordable way to manage it</strong><span>Money, people, work and files in tools that connect.</span></a></li>
                <li data-stage><a href="#tools"><strong>Your business is growing and needs better tools and processes</strong><span>Add tools as your needs change, without starting again.</span></a></li>
                <li data-stage><a href="{{ route('about') }}"><strong>You lead an established organisation</strong><span>One sign-in across connected tools, and knowledge your teams keep.</span></a></li>
            </ol>
        </div>
    </section>

    {{-- 7. The invitation: what is waiting on the other side of "Join", in one place. Only what works today; the account is free. --}}
    <section class="hp-join" id="join" aria-labelledby="join-title">
        <div class="hp-join__head">
            <h2 id="join-title" data-reveal>What opens when you join.</h2>
            <p class="lp-lede" data-reveal>Creating an InfoDot account is free. This is what is waiting on the other side.</p>
            @guest
                <p class="hp-join__actions" data-reveal><a href="{{ route('register') }}" class="ix-btn ix-btn--primary lp-btn">Join InfoDot for free</a><a href="{{ route('login') }}" class="hp-textlink">I already have an account</a></p>
            @endguest
        </div>
        <dl class="hp-join__list">
            <div data-reveal><dt>Answers when you need them</dt><dd>Ask questions, search what other businesses learned and follow researched solutions.</dd></div>
            <div data-reveal><dt>Your business, easy to find</dt><dd>A business page with your logo, photographs and what you offer, listed in the directory.</dd></div>
            <div data-reveal><dt>Your team in one place</dt><dd>Bring in the people who work with you, each with a login of their own.</dd></div>
            <div data-reveal><dt>A way to reach other businesses</dt><dd>Message businesses, book or request appointments, and keep every reply in your inbox.</dd></div>
            <div data-reveal><dt>One sign-in for the Dot tools</dt><dd>Open the tools your business uses without creating a new account for each one.</dd></div>
        </dl>
    </section>

    {{-- 8. Trust: what is true today, in plain words. Detail lives on the pages linked below. --}}
    <section class="ix-pubsec ix-pubsec--tint hp-trust" aria-labelledby="trust-title">
        <h2 id="trust-title" data-reveal>A place you can rely on</h2>
        <dl class="ix-pubfacts" data-trust>
            <div data-reveal><dt>Company first, public by choice</dt><dd>A question is visible to your company by default. It becomes public only when someone explicitly chooses that, and another company can never open it.</dd></div>
            <div data-reveal><dt>Every answer shows how it earned its place</dt><dd>Accepted by the person who asked, or verified by a moderator who did not write it. Nobody approves their own work.</dd></div>
            <div data-reveal><dt>Your name stays on what you write</dt><dd>The people who help are credited for it.</dd></div>
            <div data-reveal><dt>Guidance, not advice</dt><dd>Legal, tax and financial questions get answers from people's experience. Check them with a professional before you rely on them.</dd></div>
        </dl>
        <ul class="hp-trust__links" data-reveal>
            <li><a href="{{ route('policy') }}">How we handle your information</a></li>
            <li><a href="{{ route('terms') }}">Terms of use</a></li>
            <li><a href="{{ route('about') }}">About InfoDot</a></li>
            <li><a href="{{ route('features') }}">What it does today</a></li>
            <li><a href="{{ route('faqs') }}">Questions people ask</a></li>
        </ul>
    </section>

    {{-- 9. The close: the promise, made personal. --}}
    <section class="ix-pubcta hp-cta" aria-labelledby="cta-title">
        <div class="ix-pubcta__inner">
            <h2 id="cta-title" data-cta-title>Build your business with InfoDot in your corner.</h2>
            <p class="hp-cta__lede" data-reveal>You won't have every answer on day one. Nobody does. But you won't have to find them alone.</p>
            <div class="ix-pubhero__actions" data-reveal>
                @auth
                    <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask a question</a>
                @else
                    <a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Join InfoDot for free</a>
                    <a href="{{ route('login') }}" class="ix-btn">Sign in</a>
                @endauth
            </div>
            <p class="hp-cta__support" data-reveal>Free to join. Start where you are.</p>
        </div>
    </section>

</x-public-layout>
