@php
    // Every live platform that has a logo of its own, in the config's order. The files are the platforms' logos trimmed to their artwork, so they all fill their tile alike.
    $platforms = collect(config('ecosystem.platforms', []))
        // Only logos that carry the platform's name: the Dot.Agents mark has no wording, so it is left out of the wall.
        ->reject(fn ($p, $key) => $key === 'agents')
        ->filter(fn ($p, $key) => ($p['active'] ?? false) && ! ($p['internal'] ?? false) && $key !== 'infodot' && file_exists(public_path('img/landing/platforms/'.$key.'.png')))
        ->map(fn ($p, $key) => ['name' => $p['name'], 'logo' => asset('img/landing/platforms/'.$key.'.png')])->values();
    $examples = ['How do I register a business?', 'How do I price my work?', 'How do I find customers?', 'How do I track my cash?', 'How do I hire my first person?'];
    $search = fn (string $words) => route('solution_search_results', ['search' => $words]);
    $first = auth()->check() ? \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')->toString() : null;
@endphp
<x-public-layout landing title="InfoDot" description="Search practical business solutions from people who have done it, or ask your own question. InfoDot is where the Dot.Eco-system keeps what it knows about running a business.">
    <section class="lp-hero" aria-labelledby="hero-title">
        <img class="lp-hero__bg" src="{{ asset('img/landing/hero-1920.jpg') }}" srcset="{{ asset('img/landing/hero-960.jpg') }} 960w, {{ asset('img/landing/hero-1920.jpg') }} 1920w" sizes="100vw" width="1920" height="1280" alt="" fetchpriority="high" decoding="async">
        <div class="lp-hero__veil" aria-hidden="true"></div>
        <div class="lp-hero__inner">
            <h1 id="hero-title" class="lp-hero__title" data-hero>{{ $first ? 'Welcome back, '.$first.'.' : 'Find practical business solutions.' }}</h1>
            <p class="lp-hero__sub" data-hero>{{ $first ? 'What do you want to solve today?' : 'Search what business owners have learned, or ask your own question.' }}</p>

            <form class="lp-search" action="{{ route('solution_search_results') }}" method="GET" role="search" data-hero>
                <label for="lp-q" class="ix-sr">Search practical business solutions</label>
                <span class="material-symbols-rounded lp-search__icon" aria-hidden="true">search</span>
                <input id="lp-q" class="lp-search__input" type="search" name="search" placeholder="How do I register a business?" autocomplete="off" data-examples="{{ json_encode($examples) }}">
                <button type="submit" class="lp-search__go" aria-label="Search"><x-ix.arrow /></button>
            </form>

            <ul class="lp-chips" aria-label="Things people look up" data-hero>
                <li><a href="{{ $search('register') }}">Register a company</a></li>
                <li><a href="{{ $search('price') }}">Set a price</a></li>
                <li><a href="{{ $search('customers') }}">Find customers</a></li>
                <li><a href="{{ $search('cash') }}">Manage cash</a></li>
                <li><a href="{{ $search('hire') }}">Hire someone</a></li>
            </ul>
            <p class="lp-hero__ask" data-hero>Can't find it? <a href="{{ route('seek') }}">Ask a new question</a>.</p>
        </div>
    </section>

    <section class="lp-split" aria-labelledby="start-title">
        <figure class="lp-photo" data-reveal>
            <div class="lp-photo__frame" data-parallax>
                <img src="{{ asset('img/landing/seamstress.jpg') }}" width="1200" height="800" alt="A seamstress at her sewing machine, with racks of colourful fabric behind her" loading="lazy" decoding="async">
            </div>
        </figure>
        <div class="lp-split__body">
            <h2 id="start-title" data-reveal>Start where you are</h2>
            <p class="lp-lede" data-reveal>Come with an undeveloped idea or an established problem. Each of these opens something that works today. Those that need an account ask you to sign in first and bring you back.</p>
            <ul class="ix-intents" data-reveal>
                <li><a href="{{ route('solution_search_results', ['topic' => 'getting-started']) }}" class="ix-intent"><span><strong>I want to start a business</strong><span>Read the first steps others wrote down.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('seek') }}" class="ix-intent"><span><strong>I have a problem to solve</strong><span>Ask in plain words. You accept the answer that worked.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('solution_search_results') }}" class="ix-intent"><span><strong>I want to learn from people who have done it</strong><span>Search the public questions and solutions before you ask.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('solutions') }}" class="ix-intent"><span><strong>I need a procedure I can follow</strong><span>Solutions are numbered steps, and a moderator marks the ones they have checked.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('register') }}" class="ix-intent"><span><strong>I want to share what I know</strong><span>Your name stays on your answer, and the person who asked decides if it helped.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('login') }}" class="ix-intent"><span><strong>I want one place to open my business tools</strong><span>Sign in once and open any live Dot platform without signing in again.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            </ul>
        </div>
    </section>

    <section class="lp-split lp-split--flip lp-split--tint" aria-labelledby="alone-title">
        <div class="lp-split__body">
            <h2 id="alone-title" data-reveal>You are not figuring this out alone.</h2>
            <p class="lp-lede" data-reveal>Behind every answer is someone who has run into the same wall. You ask, people who know reply, and you decide what actually helped. What you learn is kept, filed and findable, so the next person does not start from nothing, and neither do you.</p>
            <ul class="lp-checks" data-reveal>
                <li>You choose which answer solved it. Nobody ranks your problem for you.</li>
                <li>Your company's questions stay with your company unless you make them public.</li>
                <li>You are told when someone answers, comments, accepts or verifies your work.</li>
            </ul>
            <p data-reveal><a href="{{ route('register') }}" class="ix-btn ix-btn--primary lp-btn">Create your account</a></p>
        </div>
        <figure class="lp-photo lp-photo--tall" data-reveal>
            <div class="lp-photo__frame" data-parallax>
                <img src="{{ asset('img/landing/couturiere.jpg') }}" width="1000" height="1500" alt="A smiling tailor holding up the garment she is working on" loading="lazy" decoding="async">
            </div>
        </figure>
    </section>

    <section class="lp-journey" aria-labelledby="journey-title">
        <div class="lp-journey__head">
            <h2 id="journey-title" data-reveal>Wherever you are in building your business</h2>
            <p class="lp-lede" data-reveal>Pick the stage you are in and see what people have written about it. Every one of these is a search, so what you find is whatever has been shared.</p>
        </div>
        <ol class="lp-stages" data-journey>
            <li data-stage><a href="{{ $search('idea') }}"><strong>Explore</strong><span>Test an idea before you spend money on it.</span></a></li>
            <li data-stage><a href="{{ $search('plan') }}"><strong>Plan</strong><span>Costs, funding and the first milestones.</span></a></li>
            <li data-stage><a href="{{ $search('register') }}"><strong>Establish</strong><span>Registration, suppliers, pricing and set-up.</span></a></li>
            <li data-stage><a href="{{ $search('customers') }}"><strong>Launch</strong><span>Your first customers and your first sale.</span></a></li>
            <li data-stage><a href="{{ $search('cash') }}"><strong>Operate</strong><span>Money, stock, people and the daily run.</span></a></li>
            <li data-stage><a href="{{ $search('profit') }}"><strong>Improve</strong><span>Find the bottleneck and fix it.</span></a></li>
            <li data-stage><a href="{{ $search('grow') }}"><strong>Grow</strong><span>New customers, new products, more capacity.</span></a></li>
        </ol>
    </section>

    <figure class="lp-band" data-reveal>
        <div class="lp-band__frame" data-parallax>
            <img src="{{ asset('img/landing/market.jpg') }}" width="1400" height="1050" alt="Colourful shirts and dresses hanging at a market stall in Cape Town" loading="lazy" decoding="async">
        </div>
    </figure>

    <section class="ix-pubsec" aria-labelledby="how-title">
        <h2 id="how-title" data-reveal>What InfoDot does today</h2>
        <p class="ix-pubsec__lede" data-reveal>InfoDot is a knowledge base for a business's questions and verified solutions, and it connects the platforms around it. Nothing on this page describes a feature that is not there.</p>
        <div class="ix-pillars">
            <article class="ix-pillar" data-reveal>
                <p class="ix-pillar__plate ix-pillar__plate--knowledge" aria-hidden="true">LEARN</p>
                <div>
                    <h3>Learn from collective experience</h3>
                    <p>Questions are answered by the people in your company and, where a question is made public, by the wider community. The person who asked accepts the answer that solved it, and it is stamped SOLVED. Solutions are written as steps, and a moderator can mark one as verified. There are no likes and no follower counts to chase: what counts is whether it helped.</p>
                </div>
            </article>
            <article class="ix-pillar" data-reveal>
                <p class="ix-pillar__plate ix-pillar__plate--platforms" aria-hidden="true">KEEP</p>
                <div>
                    <h3>Keep what your business learns</h3>
                    <p>Everything is filed by topic and carries a reference, so a good answer is found again instead of asked again. Your company's questions stay with your company unless someone chooses to make them public. You are told when someone answers, comments, accepts or verifies your work.</p>
                </div>
            </article>
            <article class="ix-pillar" data-reveal>
                <p class="ix-pillar__plate ix-pillar__plate--analytics" aria-hidden="true">CONNECT</p>
                <div>
                    <h3>Connect the rest of your business</h3>
                    <p>One account opens every live platform in the Dot.Eco-system: files, documents, forms, tasks and more. An analytics view shows how well your knowledge is working: what was asked, what was solved, and how many replies each question gets. Where the Dot.Eco-system's AI suggests something, a person approves it and the record shows who.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="ix-pubsec ix-pubsec--tint" aria-labelledby="trust-title">
        <h2 id="trust-title" data-reveal>Trusted answers, not noise</h2>
        <dl class="ix-pubfacts">
            <div data-reveal><dt>Company first, public by choice</dt><dd>A question is visible to your company by default. It becomes public only when someone explicitly chooses that, and another company can never open it.</dd></div>
            <div data-reveal><dt>Every answer shows how it earned its place</dt><dd>Accepted by the person who asked, or verified by a moderator who did not write it. Nobody approves their own work.</dd></div>
            <div data-reveal><dt>Hidden is recorded, not erased</dt><dd>When a moderator hides something, the reason is kept and the author can appeal.</dd></div>
            <div data-reveal><dt>Guidance, not advice</dt><dd>Legal, tax and financial questions get answers from people's experience. Check them with a professional before you rely on them.</dd></div>
        </dl>
    </section>

    <section class="ix-pubsec" aria-labelledby="eco-title">
        <h2 id="eco-title" data-reveal>The Dot.Eco-system around it</h2>
        <p class="ix-pubsec__lede" data-reveal>InfoDot is the front door to more than a dozen platforms that are built and deployed, among them:</p>
        <ul class="lp-logos" aria-label="Some Dot platforms">
            @foreach ($platforms as $platform)
                <li data-reveal><img src="{{ $platform['logo'] }}" alt="{{ $platform['name'] }}" loading="lazy" decoding="async"></li>
            @endforeach
        </ul>
        <p class="ix-pubsec__lede" data-reveal>Sign in to see which ones you can open and to go straight to them.</p>
    </section>

    <section class="ix-pubsec ix-pubsec--tint" aria-labelledby="who-title">
        <div class="lp-founder">
            <div>
                <h2 id="who-title" data-reveal>Built in the open</h2>
                <p class="ix-pubsec__lede" data-reveal>InfoDot is one platform in the Dot.Eco-system. It is built with an AI-assisted workflow: a person decides what gets built and the rules it must follow, reviews what ships, and runs it in production. We say what works today and nothing more.</p>
            </div>
            <ul class="ix-intents" data-reveal>
                <li><a href="{{ route('about') }}" class="ix-intent"><span><strong>About InfoDot</strong><span>Why it exists and how it is run.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('features') }}" class="ix-intent"><span><strong>What it does</strong><span>Everything that works today.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
                <li><a href="{{ route('faqs') }}" class="ix-intent"><span><strong>Questions people ask</strong><span>Who sees your question, and how answers are trusted.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            </ul>
        </div>
    </section>

    <section class="ix-pubcta" aria-labelledby="cta-title">
        <div class="ix-pubcta__inner">
            <h2 id="cta-title" data-reveal>Bring your first question.</h2>
            <div class="ix-pubhero__actions" data-reveal>
                @auth
                    <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask a question</a>
                @else
                    <a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Create an account</a>
                    <a href="{{ route('login') }}" class="ix-btn">Sign in</a>
                @endauth
            </div>
        </div>
    </section>

</x-public-layout>
