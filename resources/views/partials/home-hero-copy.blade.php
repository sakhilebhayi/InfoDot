        <div class="lp-hero__inner" data-hero-inner>
            <h1 id="hero-title" class="lp-hero__title" data-hero>{{ $first ? 'Welcome back, '.$first.'.' : 'Your business, written down and connected.' }}</h1>
            <p class="lp-hero__sub" data-hero>{{ $first ? 'What do you want to solve today?' : 'Practical answers from other businesses, and one account for the tools that run yours: money, people, work and documents. Free to join.' }}</p>

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
