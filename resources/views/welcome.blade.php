@php
    $examples = ['How do I register a business?', 'How do I price my work?', 'How do I find customers?', 'How do I track my cash?', 'How do I hire my first person?'];
    $search = fn (string $words) => route('solution_search_results', ['search' => $words]);
    $tools = route('business-tools');
    // R-73: the journey (Start, Manage, Connect, Grow), from the same verified data as the Business Tools page.
    $journey = \App\Support\BusinessTools::journey();
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

    {{-- 2. The dream (R-73): the person behind the business. The hero's line is not repeated here; this section answers it, then meets
         the reader wherever they are starting from, and hands over to what makes building hard. --}}
    <section class="lp-split hp-who hp-dream" id="ambition" aria-labelledby="dream-title">
        <figure class="lp-photo" data-reveal>
            <div class="lp-photo__frame" data-parallax>
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/market-vegetables-1200.jpg') }}" width="1200" height="800" alt="A vegetable stall at a Cape Town market: tomatoes, avocados, fennel, radishes and herbs in boxes" loading="lazy" decoding="async">
            </div>
        </figure>
        <div class="lp-split__body">
            <h2 id="dream-title" class="hp-dream__title" data-reveal>Every business starts with someone who decided to try.</h2>
            <p class="lp-lede" data-reveal>Maybe it began as an idea you kept coming back to. A skill people were already paying you for. The wish to work for yourself, or to build something your family can be proud of. However yours began, starting took courage, and what you are building deserves the room to become what you imagine.</p>
            <h3 class="hp-dream__sub" data-reveal>Wherever you're starting from, there's a next step.</h3>
            <ol class="hp-who__list" role="list" data-journey data-vertical>
                <li data-stage><a href="{{ route('solution_search_results', ['topic' => 'getting-started']) }}"><strong>You have a business idea and don't know where to start</strong><span>Read the first steps other people wrote down.</span></a></li>
                <li data-stage><a href="{{ $search('register') }}"><strong>You're starting your first business</strong><span>Registration, pricing and your first customers, in the words of people who did it.</span></a></li>
                <li data-stage><a href="#ecosystem"><strong>You run a small business and want an affordable way to manage it</strong><span>Money, people, work and files in tools that connect.</span></a></li>
                <li data-stage><a href="#ecosystem"><strong>Your business is growing and needs better tools and processes</strong><span>Add tools as your needs change, without starting again.</span></a></li>
                <li data-stage><a href="{{ route('about') }}"><strong>You lead an established organisation</strong><span>One sign-in across connected tools, and knowledge your teams keep.</span></a></li>
            </ol>
            <p class="hp-dream__turn" data-reveal>Ambition gets a business started. Keeping it moving, day after day, is the hard part.</p>
        </div>
    </section>

    {{-- 3. The reality (R-73, replacing R-67's heading at the owner's request). It names what makes the work hard without calling anyone
         incapable: even businesses that are doing well lose time to these. It ends by turning towards the support that follows. --}}
    <section class="hp-reality" aria-labelledby="reality-title">
        <div class="hp-reality__head">
            <h2 id="reality-title" class="hp-reality__title" data-reveal>You started this business to build something. Not to spend every day figuring everything out.</h2>
            <p class="lp-lede" data-reveal>Every owner knows the feeling, and it isn't only new businesses. Even ones that are doing well lose hours to things that should be simpler. Some of this may sound familiar.</p>
        </div>
        <ul class="hp-reality__list" role="list">
            <li data-reveal>A big decision arrives before you know who to ask, or whether the advice you found will hold up.</li>
            <li data-reveal>What you need to know is spread across a dozen sites, and none of them knows your business.</li>
            <li data-reveal>The work lives in spreadsheets, WhatsApp threads, email and a phone full of files.</li>
            <li data-reveal>You know a better tool must exist. Finding the right one is a project of its own.</li>
            <li data-reveal>The supplier, partner or customer you need is out there. You just can't see them from where you stand.</li>
            <li data-reveal>You have plans to grow, but the next step isn't always clear.</li>
        </ul>
        <p class="hp-reality__close" data-reveal><strong>None of this means you're doing it wrong.</strong> It means the knowledge, tools and people a business needs are harder to reach than they should be. It doesn't have to be this way.</p>
    </section>

    {{-- 4. The support (R-73). The answer to the reality above: what a connected ecosystem gives a business, by what it helps it do, not by
         platform name. Every line is something InfoDot or its platforms do today. --}}
    <section class="hp-support" id="support" aria-labelledby="support-title">
        <div class="hp-support__head">
            <h2 id="support-title" data-reveal>Behind every business is a dream. Behind every growing business is the right support.</h2>
            <p class="lp-lede" data-reveal>You shouldn't have to search for every answer, tool and contact on your own. InfoDot brings them closer together, so moving forward takes less searching and more building.</p>
            <figure class="hp-support__photo" data-reveal>
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/market-jars-800.jpg') }}" width="800" height="532" alt="Jars of home-made sauces and bottles of chilli sauce set out on a stall's table at an outdoor market" loading="lazy" decoding="async">
            </figure>
        </div>
        <ul class="hp-support__list" role="list">
            <li class="hp-support__item" style="--tone: var(--ix-knowledge)" data-reveal><h3>Knowledge you can act on</h3><p>Questions answered by people who have been there, and researched solutions with their sources named.</p></li>
            <li class="hp-support__item" style="--tone: var(--ix-platforms)" data-reveal><h3>Tools for the work itself</h3><p>Money, people, projects, documents and files, each in a tool built for the job.</p></li>
            <li class="hp-support__item" style="--tone: var(--ix-people)" data-reveal><h3>Other businesses within reach</h3><p>A directory to find suppliers, partners and customers, and a page of your own so they can find you.</p></li>
            <li class="hp-support__item" style="--tone: var(--ix-documents)" data-reveal><h3>Resources ready to use</h3><p>Step-by-step solutions, a researched library and the experience of businesses like yours.</p></li>
            <li class="hp-support__item" style="--tone: var(--ix-analytics)" data-reveal><h3>Room to learn and keep going</h3><p>Seminars, shared knowledge and a record of what your business has learned, so each step builds on the last.</p></li>
        </ul>
        <p class="hp-support__close" data-reveal>This isn't a pile of separate apps. It's one connected place: one account, one sign-in, and tools and knowledge that know about each other, whatever stage your business is at.</p>
    </section>

    {{-- 5. The possibility (R-73). The ecosystem by the journey a business is on: Start, Manage, Connect, Grow. Each stage shows what
         InfoDot does itself and its live platforms (App\Support\BusinessTools::journey(), the same facts the Business Tools page shows,
         set-up limits included). Without JavaScript every stage is shown in turn; with it, the stages become tabs. --}}
    <section class="hp-journey" id="ecosystem" aria-labelledby="journey-title">
        <div class="hp-journey__head">
            <h2 id="journey-title" data-reveal>Imagine what your business could become with the right things behind it.</h2>
            <p class="lp-lede" data-reveal>Start with what you need today. This is what InfoDot and its platforms can do at each stage of the journey.</p>
        </div>
        <div class="hp-journey__tabs" role="tablist" aria-label="Stages of the journey" data-journey-tabs hidden>
            @foreach ($journey as $stage)
                <button type="button" role="tab" id="stage-tab-{{ $stage['key'] }}" aria-controls="stage-{{ $stage['key'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" class="hp-journey__tab">{{ $stage['title'] }}</button>
            @endforeach
        </div>
        @foreach ($journey as $stage)
            <section class="hp-stage" id="stage-{{ $stage['key'] }}" role="tabpanel" aria-labelledby="stage-h-{{ $stage['key'] }}" data-stage-panel>
                <div class="hp-stage__intro">
                    <h3 id="stage-h-{{ $stage['key'] }}" class="hp-stage__title">{{ $stage['title'] }}</h3>
                    <p class="hp-stage__line">{{ $stage['line'] }}</p>
                    @if ($stage['infodot'] !== [])
                        <ul class="hp-stage__own" role="list">
                            @foreach ($stage['infodot'] as $item)
                                <li><a href="{{ $item['url'] }}"><strong>{{ $item['title'] }}</strong><span>{{ $item['body'] }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($stage['tools'] !== [])
                    <div class="hp-stage__tools">
                        <p class="hp-stage__label">Platforms for this stage</p>
                        <ul class="hp-stage__list" role="list">
                            @foreach ($stage['tools'] as $tool)
                                <li>
                                    <details class="hp-tool">
                                        <summary>
                                            <img src="{{ $tool['logo'] }}" alt="" width="60" height="40" loading="lazy" decoding="async">
                                            <span class="hp-tool__name">{{ $tool['name'] }}<span class="hp-tool__role">{{ $tool['role'] }}</span></span>
                                            <span class="hp-tool__value">{{ $tool['outcome'] }}</span>
                                        </summary>
                                        <dl class="hp-tool__body">
                                            <div><dt>The problem</dt><dd>{{ $tool['problem'] }}</dd></div>
                                            <div><dt>What you can do</dt><dd>{{ $tool['why'] }}</dd></div>
                                            @if (filled($tool['note'] ?? null))
                                                <div class="hp-tool__note"><dt>Good to know</dt><dd>{{ $tool['note'] }}</dd></div>
                                            @endif
                                        </dl>
                                        <a class="hp-textlink" href="{{ route('business-tools') }}#tool-{{ $tool['key'] }}">More about {{ $tool['name'] }}</a>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endforeach
        <div class="hp-journey__more" data-reveal>
            <p>Which tools you can open depends on your business and how access is arranged. Running something specialised, like a mine or a ride-hailing operation? There are tools for that too.</p>
            <a href="{{ $tools }}" class="ix-btn lp-btn">Explore Business Tools</a>
        </div>

        <div class="hp-journey__week">
            <h3 id="how-it-works" data-reveal>An ordinary week can look like this.</h3>
            <ol class="hp-steps hp-steps--week" role="list" data-journey>
                <li data-stage><span class="hp-steps__n" aria-hidden="true">Mon</span><h4><span class="ix-sr">Monday: </span>Sort out tax.</h4><p>You search "SARS" and work through a researched solution, sources beside the steps.</p></li>
                <li data-stage><span class="hp-steps__n" aria-hidden="true">Tue</span><h4><span class="ix-sr">Tuesday: </span>Send an invoice.</h4><p>You open Dot.Finance with the same sign-in and bill last week's customer.</p></li>
                <li data-stage><span class="hp-steps__n" aria-hidden="true">Wed</span><h4><span class="ix-sr">Wednesday: </span>Find a supplier.</h4><p>You find a printer in the directory and ask for an appointment from their page.</p></li>
                <li data-stage><span class="hp-steps__n" aria-hidden="true">Fri</span><h4><span class="ix-sr">Friday: </span>Write it down.</h4><p>You record how you solved this week's tricky question, so nobody solves it twice.</p></li>
            </ol>
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

    {{-- 6b. The aim (R-70): where InfoDot is going, just before what joining opens. --}}
    @include('partials.aim', ['id' => 'aim'])

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
