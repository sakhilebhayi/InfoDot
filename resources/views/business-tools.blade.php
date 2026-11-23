@php
    /*
     * R-56: the public Business Tools page. Every fact about a platform (name, tagline, capabilities, declared connections) is read from the
     * catalogue by App\Support\BusinessTools; the words around them come from config/business_tools.php. Nothing here is typed in by hand.
     */
    $tools = $page['tools'];
    $groups = $page['groups'];
    $needs = $page['needs'];
    $pillars = $page['pillars'];
    $signedIn = auth()->check();
    $join = route('register');
    $open = $signedIn ? route('ecosystem.widget') : $join;

    // The plate (stencilled code and colour) of the group a platform sits in, for its label on screen.
    $plateOf = [];
    foreach ($groups as $group) {
        foreach ($group['tools'] as $key) {
            $plateOf[$key] = $group['plate'];
        }
    }

    // How each group lays out its showcases. a/b put the screen left/right, feature gives the screen the full width, duo sits two to a row.
    $layouts = [
        'understand' => ['a', 'b'],
        'money' => ['feature'],
        'people' => ['feature'],
        'work' => ['b', 'a'],
        'information' => ['a', 'b', 'duo', 'duo', 'duo', 'duo'],
        'communicate' => ['a', 'b'],
        'customers' => ['b', 'a', 'feature'],
        'specialised' => ['a', 'b', 'a'],
    ];

    // The screens that open the page: six real product screens at different depths, around InfoDot's own gate.
    $bays = collect(['finance', 'analytics', 'projects', 'hr', 'tasks', 'docs'])->filter(fn ($key) => isset($tools[$key]))->values();
    $cta = function (array $tool) use ($signedIn): array {
        if ($signedIn) {
            return [route('platforms.show', $tool['key']), ($tool['core'] ? 'Open ' : 'Explore ').$tool['name']];
        }

        return [route('register'), ($tool['core'] ? 'Get started with ' : 'Explore ').$tool['name']];
    };
@endphp
<x-public-layout title="Business tools" motion="tools" description="Discover the connected business tools that help you start, manage and grow: finance, people, projects, documents, sales and more, from one InfoDot account.">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Business tools',
        'url' => route('business-tools'),
        'description' => 'The connected business platforms that help you start, manage and grow, from one InfoDot account.',
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => collect($tools)->values()->map(fn ($tool, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $tool['name'], 'url' => route('business-tools').'#tool-'.$tool['key'],
            ])->all(),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- 1. The opening: one ecosystem, shown as real product screens around one gate. --}}
    <div class="bt-band">
    <section class="bt-hero" aria-labelledby="bt-title">
        <div class="bt-hero__copy">
            <h1 id="bt-title" class="bt-hero__title" data-bt-hero>Your business deserves more than disconnected tools.</h1>
            <p class="bt-hero__lead" data-bt-hero>Meet the connected business platforms that help you start, manage and grow.</p>
            <p class="bt-hero__desc" data-bt-hero>From managing your finances and people to understanding performance, organising projects and discovering new opportunities, InfoDot brings together the digital tools your business needs to move forward.</p>
            <div class="bt-hero__actions" data-bt-hero>
                <a href="#platforms" class="ix-btn ix-btn--primary lp-btn">Explore the Platforms</a>
                @if ($signedIn)
                    <a href="{{ $open }}" class="ix-btn lp-btn">Open your platforms</a>
                @else
                    <a href="{{ $join }}" class="ix-btn lp-btn">Join InfoDot for Free</a>
                @endif
            </div>
        </div>

        <div class="bt-yard" aria-hidden="true" data-yard>
            @foreach ($bays as $i => $key)
                @php
                    $tool = $tools[$key];
                @endphp
                <figure class="bt-bay bt-bay--{{ $i + 1 }}" data-depth="{{ [0.5, 1.1, 0.8, 1.4, 1.7, 0.7][$i] ?? 1 }}">
                    <span class="bt-plate bt-plate--{{ $plateOf[$key]['tone'] }}">{{ $plateOf[$key]['code'] }}</span>
                    <img src="{{ $tool['screen']['src'] }}" srcset="{{ $tool['screen']['srcset'] }}" sizes="(min-width: 1100px) 36vw, 70vw" width="960" height="600" alt="" decoding="async" @if ($i > 1) loading="lazy" @endif>
                </figure>
            @endforeach
            <p class="bt-gate">
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/brand/logo_white.png') }}" alt="" width="319" height="130" decoding="async">
                <span>One account</span>
            </p>
        </div>
    </section>
    </div>

    {{-- 2. The bigger picture. Discover, Manage and Grow are the frame; each lists the platforms that serve it. --}}
    <section class="bt-picture" id="ecosystem" aria-labelledby="picture-title">
        <div class="bt-picture__intro">
            <h2 id="picture-title" data-reveal>Everything works better when your business is connected.</h2>
            <p class="lp-lede" data-reveal>Running a business means managing many different things. Finance, people, projects, operations, communication and decision-making all need attention.</p>
            <p class="lp-lede" data-reveal>Instead of navigating disconnected tools, InfoDot brings these business needs together through a growing ecosystem of specialised platforms.</p>
        </div>
        <div class="bt-pillars">
            @foreach ($pillars as $pillarKey => $pillar)
                <article class="bt-pillar bt-pillar--{{ $pillarKey }}" data-pillar aria-labelledby="bt-{{ $pillarKey }}-h">
                    <h3 id="bt-{{ $pillarKey }}-h" class="bt-pillar__word">{{ $pillar['title'] }}</h3>
                    <div class="bt-pillar__body">
                        <p class="bt-pillar__line" data-reveal>{{ $pillar['line'] }}</p>
                        <ul class="bt-pillar__tools" data-reveal>
                            @foreach (collect($tools)->filter(fn ($tool) => in_array($pillarKey, $tool['pillars'], true)) as $tool)
                                <li><a href="#tool-{{ $tool['key'] }}">{{ $tool['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- 3. Start from the need, not the product. Each need names the platforms whose catalogue capabilities cover it. --}}
    <section class="bt-finder" id="finder" aria-labelledby="finder-title">
        <div class="bt-finder__head">
            <h2 id="finder-title" data-reveal>What does your business need help with?</h2>
            <p class="lp-lede" data-reveal>Pick the need that sounds most like yours. Each one points to the platforms that cover it, and nothing is switched on or changed by looking.</p>
        </div>
        <ul class="bt-needs" role="list">
            @foreach ($needs as $need)
                <li class="bt-need" data-need data-reveal>
                    <p class="bt-need__label">{{ $need['label'] }}</p>
                    <ul class="bt-need__tools" role="list">
                        @foreach ($need['tools'] as $key)
                            <li><a href="#tool-{{ $key }}" data-need-link="{{ $key }}">{{ $tools[$key]['name'] }}</a></li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- 4. The platforms, by what a business is trying to do. Every live platform has its own showcase. --}}
    <section class="bt-platforms" id="platforms" aria-labelledby="platforms-title">
        <div class="bt-platforms__head">
            <h2 id="platforms-title" data-reveal>Find the tools your business needs.</h2>
            <p class="lp-lede" data-reveal>Each group starts with the problem it addresses. Each platform in it says what it does, why a business needs it and how it helps the business grow.</p>
        </div>

        <div class="bt-platforms__layout">
            <nav class="bt-rail" aria-label="Tool groups" data-rail>
                <ul role="list">
                    @foreach ($groups as $group)
                        <li><a href="#group-{{ $group['key'] }}" data-rail-link="{{ $group['key'] }}"><span class="bt-plate bt-plate--{{ $group['plate']['tone'] }}">{{ $group['plate']['code'] }}</span><span class="bt-rail__title">{{ $group['title'] }}</span></a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="bt-groups">
                @foreach ($groups as $group)
                    <section class="bt-group" id="group-{{ $group['key'] }}" aria-labelledby="group-{{ $group['key'] }}-h" data-group>
                        <header class="bt-group__head">
                            <span class="bt-plate bt-plate--{{ $group['plate']['tone'] }} bt-plate--lg" aria-hidden="true">{{ $group['plate']['code'] }}</span>
                            <h3 id="group-{{ $group['key'] }}-h" data-reveal>{{ $group['title'] }}</h3>
                            <p class="bt-group__problem" data-reveal>{{ $group['problem'] }}</p>
                            <p class="bt-group__together" data-reveal>{{ $group['together'] }}</p>
                        </header>

                        <div class="bt-group__list">
                            @foreach ($group['tools'] as $index => $key)
                                @php
                                    $tool = $tools[$key];
                                    $variant = $layouts[$group['key']][$index] ?? 'a';
                                    [$ctaHref, $ctaLabel] = $cta($tool);
                                @endphp
                                <article class="bt-tool bt-tool--{{ $variant }}" id="tool-{{ $key }}" aria-labelledby="tool-{{ $key }}-h" data-tool>
                                    <figure class="bt-tool__screen" data-screen>
                                        <div class="bt-frame">
                                            <img src="{{ $tool['screen']['src'] }}" srcset="{{ $tool['screen']['srcset'] }}" sizes="(min-width: 1100px) {{ $variant === 'feature' ? '900px' : ($variant === 'duo' ? '420px' : '560px') }}, 92vw" width="960" height="600" alt="A screen from {{ $tool['name'] }}, shown with demonstration data" loading="lazy" decoding="async">
                                        </div>
                                        <figcaption>{{ $tool['name'] }}, with demonstration data</figcaption>
                                    </figure>

                                    <div class="bt-tool__body">
                                        <p class="bt-tool__id"><img src="{{ $tool['logo'] }}" alt="{{ $tool['name'] }}" loading="lazy" decoding="async"><span class="bt-tool__role">{{ $tool['role'] }}</span></p>
                                        <h4 id="tool-{{ $key }}-h" class="bt-tool__headline">{{ $tool['headline'] }}</h4>
                                        <p class="bt-tool__tagline">{{ $tool['tagline'] }}.</p>

                                        <dl class="bt-tool__story">
                                            <div><dt>The everyday problem</dt><dd>{{ $tool['problem'] }}</dd></div>
                                            <div><dt>Why your business needs it</dt><dd>{{ $tool['why'] }}</dd></div>
                                        </dl>

                                        <div class="bt-more">
                                            <input type="checkbox" id="more-{{ $key }}" class="bt-more__check">
                                            <label for="more-{{ $key }}" class="bt-more__label"><span>What {{ $tool['name'] }} does and how it helps you grow</span><span class="material-symbols-rounded" aria-hidden="true">expand_more</span></label>
                                            <div class="bt-more__body">
                                            <p class="bt-tool__label">What it does</p>
                                            <ul class="lp-checks bt-tool__caps" role="list">
                                                @foreach ($tool['capabilities'] as $capability)
                                                    <li>{{ $capability }}</li>
                                                @endforeach
                                            </ul>

                                            <dl class="bt-tool__story bt-tool__story--grow">
                                                <div><dt>How it helps you grow</dt><dd>{{ $tool['growth'] }}</dd></div>
                                            </dl>

                                            @isset($tool['note'])
                                                <p class="bt-tool__note">{{ $tool['note'] }}</p>
                                            @endisset

                                            <p class="bt-tool__outcome">{{ $tool['outcome'] }}</p>

                                            @if ($tool['connects'] !== [])
                                                @php
                                                    $works = collect($tool['connects'])->map(fn ($link) => isset($tools[$link['key']])
                                                        ? '<a href="#tool-'.e($link['key']).'">'.e($link['name']).'</a>' : e($link['name']))->all();
                                                    $lastWork = array_pop($works);
                                                    $worksHtml = $works === [] ? $lastWork : implode(', ', $works).' and '.$lastWork;
                                                @endphp
                                                <p class="bt-tool__works">Connects with {!! $worksHtml !!}.</p>
                                            @endif
                                            </div>
                                        </div>

                                        <p class="bt-tool__cta"><a href="{{ $ctaHref }}" class="ix-btn lp-btn">{{ $ctaLabel }}</a></p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. How access works: one account, and what decides what you can open. --}}
    <section class="bt-access" id="access" aria-labelledby="access-title">
        <div class="bt-access__head">
            <h2 id="access-title" data-reveal>One account. A connected business ecosystem.</h2>
            <p class="lp-lede" data-reveal>InfoDot is the starting point for discovering and opening the Dot platforms. You register once, here, and you never need a separate sign-up for each tool.</p>
        </div>
        <ol class="bt-access__steps" role="list" data-journey>
            <li data-stage><h3>Register through InfoDot.</h3><p>One free account is the way in to every platform. Nothing else is created behind your back.</p></li>
            <li data-stage><h3>Discover the tools that fit.</h3><p>Look at what your business needs and see the platforms that cover it. Looking does not switch anything on.</p></li>
            <li data-stage><h3>Access depends on your arrangements.</h3><p>What you can open depends on your business, who you are and how access to each platform is arranged. Not every tool comes with every account.</p></li>
            <li data-stage><h3>Add more as you grow.</h3><p>Start with what you need today. As your business changes, you can discover and add other tools from the same account.</p></li>
        </ol>
    </section>

    {{-- 6. The close. --}}
    <section class="ix-pubcta bt-cta" aria-labelledby="bt-cta-title">
        <div class="ix-pubcta__inner">
            <h2 id="bt-cta-title" data-cta-title>Your business has room to grow. Give it the tools to get there.</h2>
            <p class="hp-cta__lede" data-reveal>Discover a connected ecosystem of business solutions designed to help you organise your operations, make better decisions and build for the future.</p>
            <div class="ix-pubhero__actions" data-reveal>
                @if ($signedIn)
                    <a href="{{ $open }}" class="ix-btn ix-btn--primary">Open your platforms</a>
                @else
                    <a href="{{ $join }}" class="ix-btn ix-btn--primary">Join InfoDot for Free</a>
                @endif
                <a href="#platforms" class="ix-btn">Explore All Platforms</a>
            </div>
            <p class="hp-cta__support" data-reveal>Start small. Add tools as your needs change.</p>
        </div>
    </section>
</x-public-layout>
