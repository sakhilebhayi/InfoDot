        <div class="lp-hero__inner" data-hero-inner>
            <h1 id="hero-title" class="lp-hero__title" data-hero>{{ $first ? 'Welcome back, '.$first.'.' : 'A better, more connected way to build your business.' }}</h1>
            @if ($first)
                <p class="lp-hero__sub" data-hero>What do you want to solve today?</p>
            @else
                {{-- R-73: the promise, then the dream it is for, then in one line what InfoDot is. --}}
                <p class="lp-hero__sub hd__dream" data-hero>That business you've been dreaming about? Let's give it somewhere to grow.</p>
                <p class="lp-hero__ctx" data-hero>InfoDot brings business knowledge, tools and other businesses together in one free account.</p>
            @endif

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
                    <a href="{{ route('register') }}" class="ix-btn ix-btn--primary lp-btn">Join InfoDot for free</a>
                    <a href="#ecosystem" class="ix-btn lp-btn">Explore the ecosystem</a>
                @endauth
            </div>
        </div>
