@php
    $platforms = collect(config('ecosystem.platforms', []))
        ->filter(fn ($p, $key) => ($p['active'] ?? false) && ! ($p['internal'] ?? false) && $key !== 'infodot')
        ->map(fn ($p) => $p['name'])->values();
@endphp
<x-public-layout landing title="InfoDot" description="InfoDot helps people start, run and grow a business: ask questions, learn from people who have been there, follow verified solutions, and open every Dot platform with one account.">
    <section class="ix-pubhero" aria-labelledby="hero-title">
        <div class="ix-pubhero__inner">
            <div class="ix-pubhero__copy">
                <h1 id="hero-title">Build your business with collective intelligence.</h1>
                <p class="ix-pubhero__lede">Starting a business is easier when you do not have to figure everything out alone. Ask a question, learn from people who have been there, and follow solutions a moderator has checked. InfoDot is where the Dot.Eco-system keeps what it knows about running a business, and the one account that opens every Dot platform.</p>
                <div class="ix-pubhero__actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="ix-btn ix-btn--primary">Open InfoDot</a>
                    @else
                        <a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Start building your business</a>
                        <a href="{{ route('solution_search_results') }}" class="ix-btn">Explore questions and answers</a>
                    @endauth
                </div>
                @guest<p class="ix-pubhero__small">Already have an account? <a href="{{ route('login') }}">Sign in</a>.</p>@endguest
            </div>

            <figure class="ix-pubspecimen" aria-label="An example of how a question looks in InfoDot">
                <ul class="ix-qlist">
                    <li class="ix-q is-solved">
                        <span class="ix-q__count" aria-hidden="true">
                            <span class="ix-stencil-num">03</span>
                            <small>replies</small>
                        </span>
                        <div class="ix-q__body">
                            <span class="ix-q__title">How do I register a clothing brand?</span>
                            <p class="ix-q__meta"><span class="ix-ref">NWFN-Q-000014</span><span>Your company</span></p>
                        </div>
                        <span class="ix-q__state is-done">Solved</span>
                    </li>
                </ul>
                <figcaption>An example. Every question and solution carries your company's code and a reference, so it can be found, quoted and trusted.</figcaption>
            </figure>
        </div>
    </section>

    <section class="ix-pubsec" aria-labelledby="start-title">
        <h2 id="start-title">Start where you are</h2>
        <p class="ix-pubsec__lede">Come with an undeveloped idea or an established problem. Each of these opens something that works today. Those that need an account ask you to sign in first and bring you back.</p>
        <ul class="ix-intents">
            <li><a href="{{ route('solution_search_results', ['topic' => 'getting-started']) }}" class="ix-intent"><span><strong>I want to start a business</strong><span>Read the first steps others wrote down: setting up a company, inviting people, finding your way around.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            <li><a href="{{ route('seek') }}" class="ix-intent"><span><strong>I have a problem to solve</strong><span>Ask in plain words. Someone who has faced it can answer, and you accept the answer that worked.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            <li><a href="{{ route('solution_search_results') }}" class="ix-intent"><span><strong>I want to learn from people who have done it</strong><span>Search the public questions and solutions before you ask. Most problems have been met before.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            <li><a href="{{ route('solutions') }}" class="ix-intent"><span><strong>I need a procedure I can follow</strong><span>Solutions are written as numbered steps, and a moderator marks the ones they have checked.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            <li><a href="{{ route('register') }}" class="ix-intent"><span><strong>I want to share what I know</strong><span>Answer a question or write a solution. Your name stays on it and the person who asked decides if it helped.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
            <li><a href="{{ route('login') }}" class="ix-intent"><span><strong>I want one place to open my business tools</strong><span>Sign in once and open any live Dot platform without signing in again.</span></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a></li>
        </ul>
    </section>

    <section class="ix-pubsec ix-pubsec--tint" aria-labelledby="how-title">
        <h2 id="how-title">What InfoDot does today</h2>
        <p class="ix-pubsec__lede">InfoDot is a knowledge base for a business's questions and verified solutions, and it connects the platforms around it. Nothing on this page describes a feature that is not there.</p>
        <div class="ix-pillars">
            <article class="ix-pillar">
                <p class="ix-pillar__plate ix-pillar__plate--knowledge" aria-hidden="true">LEARN</p>
                <div>
                    <h3>Learn from collective experience</h3>
                    <p>Questions are answered by the people in your company and, where a question is made public, by the wider community. The person who asked accepts the answer that solved it, and it is stamped SOLVED. Solutions are written as steps, and a moderator can mark one as verified. There are no likes and no follower counts to chase: what counts is whether it helped.</p>
                </div>
            </article>
            <article class="ix-pillar">
                <p class="ix-pillar__plate ix-pillar__plate--platforms" aria-hidden="true">KEEP</p>
                <div>
                    <h3>Keep what your business learns</h3>
                    <p>Everything is filed by topic and carries a reference, so a good answer is found again instead of asked again. Your company's questions stay with your company unless someone chooses to make them public. You are told when someone answers, comments, accepts or verifies your work.</p>
                </div>
            </article>
            <article class="ix-pillar">
                <p class="ix-pillar__plate ix-pillar__plate--analytics" aria-hidden="true">CONNECT</p>
                <div>
                    <h3>Connect the rest of your business</h3>
                    <p>One account opens every live platform in the Dot.Eco-system: files, documents, forms, tasks and more. An analytics view shows how well your knowledge is working: what was asked, what was solved, and how many replies each question gets. Where the Dot.Eco-system's AI suggests something, a person approves it and the record shows who.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="ix-pubsec" aria-labelledby="trust-title">
        <h2 id="trust-title">Trusted answers, not noise</h2>
        <dl class="ix-pubfacts">
            <div><dt>Company first, public by choice</dt><dd>A question is visible to your company by default. It becomes public only when someone explicitly chooses that, and another company can never open it.</dd></div>
            <div><dt>Every answer shows how it earned its place</dt><dd>Accepted by the person who asked, or verified by a moderator who did not write it. Nobody approves their own work.</dd></div>
            <div><dt>Hidden is recorded, not erased</dt><dd>When a moderator hides something, the reason is kept and the author can appeal.</dd></div>
            <div><dt>Guidance, not advice</dt><dd>Legal, tax and financial questions get answers from people's experience. Check them with a professional before you rely on them.</dd></div>
        </dl>
    </section>

    <section class="ix-pubsec ix-pubsec--tint" aria-labelledby="eco-title">
        <h2 id="eco-title">The Dot.Eco-system around it</h2>
        <p class="ix-pubsec__lede">InfoDot is the front door to more than a dozen platforms that are built and deployed, among them:</p>
        <ul class="ix-platlist" aria-label="Some Dot platforms">
            @foreach ($platforms->take(14) as $name)<li>{{ $name }}</li>@endforeach
        </ul>
        <p class="ix-pubsec__lede">Sign in to see which ones you can open and to go straight to them.</p>
    </section>

    <section class="ix-pubsec" aria-labelledby="who-title">
        <h2 id="who-title">Built by one founder, in the open</h2>
        <p class="ix-pubsec__lede">InfoDot is designed and run by Sakhile Bhayi, a mine control systems engineer and full stack developer turned founder, in South Africa. It is built with an AI-assisted workflow: a person decides what gets built and the rules it must follow, reviews what ships, and runs it in production. <a href="{{ route('about') }}">Read more about InfoDot</a>.</p>
    </section>

    <section class="ix-pubcta" aria-labelledby="cta-title">
        <div class="ix-pubcta__inner">
            <h2 id="cta-title">Bring your first question.</h2>
            <div class="ix-pubhero__actions">
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
