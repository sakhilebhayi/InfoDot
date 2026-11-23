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
<x-public-layout landing title="InfoDot" description="InfoDot brings together practical business knowledge, people who share what works, and digital tools that help you start, manage and grow your business.">
    {{-- Who publishes this site, for search engines (R-17 §13.3). Facts only: name, address, contact page. --}}
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'Organization', 'name' => 'InfoDot', 'url' => route('welcome'), 'logo' => asset('icon-512.png')],
            ['@type' => 'WebSite', 'name' => 'InfoDot', 'url' => route('welcome')],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- 1. The hero says what InfoDot is, what it does for you and what to do next. The search box stays: it is the quickest way in. --}}
    <section class="lp-hero" aria-labelledby="hero-title">
        <img class="lp-hero__bg" src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/hero-1920.jpg') }}" srcset="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/hero-960.jpg') }} 960w, {{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/hero-1920.jpg') }} 1920w" sizes="100vw" width="1920" height="1280" alt="" fetchpriority="high" decoding="async">
        <div class="lp-hero__veil" aria-hidden="true"></div>
        <div class="lp-hero__inner" data-hero-inner>
            <h1 id="hero-title" class="lp-hero__title" data-hero>{{ $first ? 'Welcome back, '.$first.'.' : 'Your business, all in one place.' }}</h1>
            <p class="lp-hero__sub" data-hero>{{ $first ? 'What do you want to solve today?' : 'Knowledge, people and tools to start, manage and grow your business.' }}</p>

            <form class="lp-search" action="{{ route('solution_search_results') }}" method="GET" role="search" data-hero>
                <label for="lp-q" class="ix-sr">Search practical business solutions</label>
                <span class="material-symbols-rounded lp-search__icon" aria-hidden="true">search</span>
                <input id="lp-q" class="lp-search__input" type="search" name="search" placeholder="How do I register a business?" autocomplete="off" data-examples="{{ json_encode($examples) }}">
                <button type="submit" class="lp-search__go" aria-label="Search"><x-ix.arrow /></button>
            </form>

            <div class="hp-hero__actions" data-hero>
                @auth
                    <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary lp-btn">Ask a question</a>
                    <a href="{{ route('dashboard') }}" class="ix-btn lp-btn">Go to your Home</a>
                @else
                    <a href="{{ route('register') }}" class="ix-btn ix-btn--primary lp-btn">Join InfoDot for Free</a>
                    <a href="#how-it-works" class="ix-btn lp-btn">See how it works</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- 2. Three ways InfoDot helps. Discover, Manage and Grow are the frame the rest of the page hangs on. --}}
    <section class="hp-pillars" id="pillars" aria-labelledby="pillars-title">
        <div class="hp-pillars__intro">
            <h2 id="pillars-title" data-reveal>What you can do with InfoDot</h2>
            <p class="lp-lede" data-reveal>Find answers, run your business from connected tools, and grow with what you learn. Start with whichever you need today.</p>
        </div>

        <div class="hp-pillars__list">
            <article class="hp-pillar hp-pillar--discover" id="discover" data-pillar aria-labelledby="discover-title">
                <h3 id="discover-title" class="hp-pillar__plate"><span class="hp-pillar__word">Discover</span><span class="hp-pillar__tag">Learn what works</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>Find practical answers, business guides, proven solutions and experiences shared by other entrepreneurs and businesses.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Ask business-related questions.</li>
                        <li>Find answers to common business challenges.</li>
                        <li>Learn from other businesses' experiences.</li>
                        <li>Discover verified solutions and practical guidance.</li>
                        <li>Share knowledge and contribute solutions.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal><a href="{{ route('solution_search_results') }}" class="ix-btn lp-btn">Search business solutions</a><a href="{{ route('seek') }}" class="hp-textlink">Ask a question</a></p>
                    <nav class="hp-stages" aria-label="Start from the stage you are in" data-reveal>
                        <p class="hp-stages__label">Start from where you are</p>
                        <ul>
                            <li><a href="{{ $search('idea') }}">Explore</a></li>
                            <li><a href="{{ $search('plan') }}">Plan</a></li>
                            <li><a href="{{ $search('register') }}">Establish</a></li>
                            <li><a href="{{ $search('customers') }}">Launch</a></li>
                            <li><a href="{{ $search('cash') }}">Operate</a></li>
                            <li><a href="{{ $search('profit') }}">Improve</a></li>
                            <li><a href="{{ $search('grow') }}">Grow</a></li>
                        </ul>
                    </nav>
                </div>
            </article>

            <article class="hp-pillar hp-pillar--manage" id="manage" data-pillar aria-labelledby="manage-title">
                <h3 id="manage-title" class="hp-pillar__plate"><span class="hp-pillar__word">Manage</span><span class="hp-pillar__tag">Run your business</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>Access the digital tools you need to organise, manage and operate your business from one connected ecosystem.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Finance and invoicing.</li>
                        <li>Human resources.</li>
                        <li>Projects and tasks.</li>
                        <li>Documents and files.</li>
                        <li>Business analytics and reporting.</li>
                        <li>Communication and collaboration.</li>
                        <li>Other specialised business operations.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal><a href="#tools" class="ix-btn lp-btn">See the tools by goal</a></p>
                </div>
            </article>

            <article class="hp-pillar hp-pillar--grow" id="grow" data-pillar aria-labelledby="grow-title">
                <h3 id="grow-title" class="hp-pillar__plate"><span class="hp-pillar__word">Grow</span><span class="hp-pillar__tag">Build your future</span></h3>
                <div class="hp-pillar__body">
                    <p class="hp-pillar__lede" data-reveal>Use knowledge, connected tools and shared experiences to improve how you work, make better decisions and discover opportunities to grow.</p>
                    <ul class="lp-checks hp-pillar__list">
                        <li>Track business progress where supported.</li>
                        <li>Improve business processes.</li>
                        <li>Learn from other businesses.</li>
                        <li>Identify operational challenges.</li>
                        <li>Discover additional tools as your business needs evolve.</li>
                    </ul>
                    <p class="hp-pillar__actions" data-reveal><a href="{{ route('businesses.index') }}" class="ix-btn lp-btn">Browse businesses</a></p>
                </div>
            </article>
        </div>
    </section>

    {{-- 3. How it works: three steps, no jargon. The line fills as the visitor scrolls through them. --}}
    <section class="hp-how" id="how-it-works" aria-labelledby="how-title">
        <div class="hp-how__head">
            <h2 id="how-title" data-reveal>Getting started with InfoDot is simple.</h2>
        </div>
        <ol class="hp-steps" role="list" data-journey>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">1</span>
                <h3>Create your account.</h3>
                <p>Join InfoDot for free and tell us about yourself, your business or your business idea.</p>
            </li>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">2</span>
                <h3>Discover what you need.</h3>
                <p>Explore business solutions, learn from other people and access the digital tools relevant to your business.</p>
            </li>
            <li data-stage>
                <span class="hp-steps__n" aria-hidden="true">3</span>
                <h3>Build and grow.</h3>
                <p>Put what you learn into action, manage your business and continuously improve as your business develops.</p>
            </li>
        </ol>
        @guest
            <p class="hp-how__cta" data-reveal><a href="{{ route('register') }}" class="ix-btn ix-btn--primary lp-btn">Join InfoDot for Free</a></p>
        @endguest
    </section>

    {{-- 4. The tools, by what you want to get done. Names are there for recognition, not as a directory to read. --}}
    <section class="hp-tools" id="tools" aria-labelledby="tools-title">
        <div class="hp-tools__head">
            <h2 id="tools-title" data-reveal>Your business. Your tools. One connected ecosystem.</h2>
            <p class="lp-lede" data-reveal>Tools are grouped by what you want to get done. You find and open the ones that fit your business. What you can open depends on your business, who you are and how access is arranged, and not every tool comes with every account.</p>
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

    {{-- 5. The collective knowledge advantage: what makes InfoDot more than a set of tools. --}}
    <section class="lp-split lp-split--flip lp-split--tint hp-know" aria-labelledby="alone-title">
        <div class="lp-split__body">
            <h2 id="alone-title" data-reveal>You don't have to figure it all out yourself.</h2>
            <p class="lp-lede" data-reveal>Every business faces challenges. InfoDot helps you find practical answers, learn from the experiences of others and contribute solutions that can help businesses facing similar situations.</p>
            <dl class="hp-know__list">
                <div data-reveal><dt>Questions and answers</dt><dd>Ask in plain words. People who have been there reply.</dd></div>
                <div data-reveal><dt>Accepted solutions</dt><dd>You choose the answer that solved it.</dd></div>
                <div data-reveal><dt>Verified business knowledge</dt><dd>A moderator who did not write a solution can mark the steps they have checked as verified.</dd></div>
                <div data-reveal><dt>Community contributions</dt><dd>Your name stays on what you share, and people are told when it helps.</dd></div>
                <div data-reveal><dt>Practical guides and resources</dt><dd>Researched solutions with named, dated sources, written as steps you can follow.</dd></div>
                <div data-reveal><dt>Knowledge that gets better</dt><dd>Solutions can be improved over time, and the earlier versions are kept.</dd></div>
            </dl>
            <p data-reveal class="hp-know__lead">InfoDot is not just a place to post questions. It turns what businesses learn into knowledge the next business can use.</p>
        </div>
        <figure class="lp-photo lp-photo--tall" data-reveal>
            <div class="lp-photo__frame" data-parallax>
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/couturiere.jpg') }}" width="1000" height="1500" alt="A smiling tailor holding up the garment she is working on" loading="lazy" decoding="async">
            </div>
        </figure>
    </section>

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
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/seamstress.jpg') }}" width="1200" height="800" alt="A seamstress at her sewing machine, with racks of colourful fabric behind her" loading="lazy" decoding="async">
            </div>
        </figure>
        <div class="lp-split__body">
            <h2 id="who-title" data-reveal>Built for every stage of your business journey.</h2>
            <p class="lp-lede" data-reveal>You don't need a company, a plan or any experience to join. Start with where you are.</p>
            <ol class="hp-who__list" role="list" data-journey data-vertical>
                <li data-stage><a href="{{ route('solution_search_results', ['topic' => 'getting-started']) }}"><strong>You have a business idea and don't know where to start</strong><span>Read the first steps other people wrote down.</span></a></li>
                <li data-stage><a href="{{ $search('register') }}"><strong>You're starting your first business</strong><span>Registration, pricing and your first customers, in the words of people who did it.</span></a></li>
                <li data-stage><a href="#tools"><strong>You run a small business and want an affordable way to manage it</strong><span>Money, people, work and files in tools that connect.</span></a></li>
                <li data-stage><a href="#tools"><strong>Your business is growing and needs better tools and processes</strong><span>Add tools as your needs change, without starting again.</span></a></li>
                <li data-stage><a href="{{ route('about') }}"><strong>You lead an established organisation</strong><span>One sign-in across connected tools, and knowledge your teams keep.</span></a></li>
            </ol>
        </div>
    </section>

    <figure class="lp-band" data-reveal>
        <div class="lp-band__frame" data-parallax>
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/market.jpg') }}" width="1400" height="1050" alt="Colourful shirts and dresses hanging at a market stall in Cape Town" loading="lazy" decoding="async">
        </div>
    </figure>

    {{-- 7. Trust: what is true today, in plain words. Detail lives on the pages linked below. --}}
    <section class="ix-pubsec ix-pubsec--tint hp-trust" aria-labelledby="trust-title">
        <h2 id="trust-title" data-reveal>A place you can rely on</h2>
        <dl class="ix-pubfacts" data-trust>
            <div data-reveal><dt>Your own account and business profile</dt><dd>You sign up with your own account, and a business can have a profile so people know who they are learning from.</dd></div>
            <div data-reveal><dt>Company first, public by choice</dt><dd>A question is visible to your company by default. It becomes public only when someone explicitly chooses that, and another company can never open it.</dd></div>
            <div data-reveal><dt>Every answer shows how it earned its place</dt><dd>Accepted by the person who asked, or verified by a moderator who did not write it. Nobody approves their own work.</dd></div>
            <div data-reveal><dt>Your name stays on what you write</dt><dd>The people who help are credited for it.</dd></div>
            <div data-reveal><dt>One place to look</dt><dd>Find what other businesses learned here, instead of searching the open internet and hoping.</dd></div>
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

    {{-- 8. The close. --}}
    <section class="ix-pubcta hp-cta" aria-labelledby="cta-title">
        <div class="ix-pubcta__inner">
            <h2 id="cta-title" data-cta-title>Your business journey starts here.</h2>
            <p class="hp-cta__lede" data-reveal>Whether you're starting with an idea, managing an existing business or looking for ways to grow, InfoDot gives you a place to find guidance, discover solutions and access the tools you need.</p>
            <div class="ix-pubhero__actions" data-reveal>
                @auth
                    <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask a question</a>
                @else
                    <a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Join InfoDot for Free</a>
                    <a href="{{ route('login') }}" class="ix-btn">Sign in</a>
                @endauth
            </div>
            <p class="hp-cta__support" data-reveal>Start where you are. Build at your own pace. Grow with InfoDot.</p>
        </div>
    </section>

</x-public-layout>
