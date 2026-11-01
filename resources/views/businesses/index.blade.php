<x-app-layout>
    @php
        $results = $search['results'];
        $reasons = $search['reasons'];
        $here = array_filter(['q' => $f['q'] ?? null, 'category' => $f['category'] ?? null, 'location' => $f['location'] ?? null, 'size' => $f['size'] ?? null,
            'type' => $f['type'] ?? null, 'verified' => $f['verified'] ? 1 : null, 'sort' => $f['sort'] ?? null, 'all' => ($f['all'] ?? false) ? 1 : null]);
        $category = $categories->firstWhere('slug', $f['category'] ?? null);
    @endphp

    @push('meta')
        <meta name="description" content="Find businesses on InfoDot by what they do, where they work and what they offer.">
        <link rel="canonical" href="{{ route('businesses.index') }}">
    @endpush

    <header class="ix-dir__head">
        <h1 class="ix-h1">Businesses</h1>
        <p class="ix-lede">Find a business by what it does, where it works or what it offers. Ask the way you would ask a person: “accounting firms in Johannesburg”.</p>

        <form class="ix-dir__form" method="get" action="{{ route('businesses.index') }}" role="search" aria-label="Search businesses">
            <div class="ix-dir__bar">
                <span class="material-symbols-rounded" aria-hidden="true">search</span>
                <label class="sr-only" for="dir-q">What do you need, and where?</label>
                <input class="ix-input" type="search" id="dir-q" name="q" maxlength="120" value="{{ $f['q'] ?? '' }}" placeholder="What do you need, and where?" autocomplete="off" @if(! $searching) autofocus @endif>
                <button type="submit" class="ix-btn ix-btn--primary">Search</button>
            </div>

            @php($filtering = collect($f)->only(['category', 'location', 'size', 'type', 'sort'])->filter()->isNotEmpty() || $f['verified'])
            <details class="ix-dir__more" open data-fold-on-phone @if($filtering) data-active @endif>
            <summary class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">tune</span>Filters @if($filtering)<span class="ix-hint">in use</span>@endif</summary>
            <div class="ix-dir__filters">
                <label class="ix-dir__filter">
                    <span>Category</span>
                    <select class="ix-input" name="category">
                        <option value="">Any</option>
                        @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(($f['category'] ?? null) === $c->slug)>{{ $c->name }}</option>@endforeach
                    </select>
                </label>
                <label class="ix-dir__filter">
                    <span>Location</span>
                    <input class="ix-input" name="location" maxlength="80" value="{{ $f['location'] ?? '' }}" placeholder="Town, city or province">
                </label>
                <label class="ix-dir__filter">
                    <span>Type</span>
                    <select class="ix-input" name="type">
                        <option value="">Any</option>
                        @foreach ($types as $value => $label)<option value="{{ $value }}" @selected(($f['type'] ?? null) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                @if ($sizes->isNotEmpty())
                    <label class="ix-dir__filter">
                        <span>Size</span>
                        <select class="ix-input" name="size">
                            <option value="">Any</option>
                            @foreach ($sizes as $size)<option value="{{ $size }}" @selected(($f['size'] ?? null) === $size)>{{ $size }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <label class="ix-dir__filter">
                    <span>Order</span>
                    <select class="ix-input" name="sort">
                        <option value="">Best match</option>
                        <option value="newest" @selected(($f['sort'] ?? null) === 'newest')>Newest on InfoDot</option>
                        <option value="name" @selected(($f['sort'] ?? null) === 'name')>Name, A to Z</option>
                    </select>
                </label>
                <label class="ix-dir__check"><input type="checkbox" name="verified" value="1" @checked($f['verified'])> Verified by InfoDot only</label>
                <input type="hidden" name="view" value="{{ $view }}">
                <button type="submit" class="ix-btn">Apply</button>
            </div>
            </details>
        </form>
    </header>

    @if ($guest)
        <p class="ix-hint ix-guestnote" role="note">You are seeing businesses listed for everyone. <a href="{{ route('login') }}">Sign in</a> to also see the ones listed for InfoDot users.</p>
    @endif

    @if ($searching)
        <section class="ix-dir__found" aria-labelledby="found-h">
            <div class="ix-dir__sum">
                <div>
                    <h2 id="found-h" class="ix-dir__count"><span class="ix-stencil-num">{{ $results->total() }}</span> {{ \Illuminate\Support\Str::plural('business', $results->total()) }}</h2>
                    <p class="ix-hint">
                        {!! collect([
                            $search['words'] ? 'Looking for <b>'.e(implode(', ', $search['words'])).'</b>' : 'All listed businesses',
                            $category ? 'in the category <b>'.e($category->name).'</b>' : null,
                            $search['location'] ? 'located in or serving <b>'.e($search['location']).'</b>' : null,
                        ])->filter()->implode(' ').($f['verified'] ? ', verified only' : '').'.' !!}
                        <a href="{{ route('businesses.index', ['view' => $view === 'grid' ? null : $view]) }}">Start again</a>
                    </p>
                </div>
                <div class="ix-filters" role="group" aria-label="Show results as">
                    <a class="ix-pill" href="{{ route('businesses.index', $here + ['view' => 'grid']) }}" @if($view === 'grid') aria-current="true" @endif><span class="material-symbols-rounded" aria-hidden="true">grid_view</span>Grid</a>
                    <a class="ix-pill" href="{{ route('businesses.index', $here + ['view' => 'list']) }}" @if($view === 'list') aria-current="true" @endif><span class="material-symbols-rounded" aria-hidden="true">view_list</span>List</a>
                </div>
            </div>

            @if ($results->isEmpty())
                <div class="ix-empty">
                    <h2>No listed business matches that yet</h2>
                    <p>Try fewer words, a nearby town, or a wider category. Only businesses that chose to be listed appear here{{ $guest ? ', and you are seeing the ones listed for everyone' : '' }}.</p>
                    <a class="ix-btn" href="{{ route('businesses.index') }}">Browse all businesses</a>
                </div>
            @else
                <ul class="ix-dir__results {{ $view === 'list' ? 'is-list' : 'is-grid' }}">
                    @foreach ($results as $team)
                        @include('businesses._card', ['team' => $team, 'why' => $reasons[$team->id] ?? []])
                    @endforeach
                </ul>
                @include('partials.pager', ['pager' => $results, 'label' => 'Businesses'])
            @endif
        </section>
    @else
        <section class="ix-dir__browse" aria-labelledby="cat-h">
            <h2 id="cat-h" class="ix-dir__h2">Browse by category</h2>
            <ul class="ix-dir__cats">
                @foreach ($browse['categories'] as $c)
                    <li>
                        <a href="{{ route('businesses.index', ['category' => $c->slug]) }}" @class(['is-empty' => $c->teams_count === 0])>
                            <span class="ix-dir__catname">{{ $c->name }}</span>
                            <span class="ix-dir__catn">{{ $c->teams_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($browse['services']->isNotEmpty())
            <section class="ix-dir__browse" aria-labelledby="svc-h">
                <h2 id="svc-h" class="ix-dir__h2">Services on offer</h2>
                <ul class="ix-dir__svcs">
                    @foreach ($browse['services'] as $s)
                        <li><a href="{{ route('businesses.index', ['q' => $s->name]) }}">{{ $s->name }} <span>{{ $s->businesses }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="ix-dir__browse" aria-labelledby="new-h">
            <h2 id="new-h" class="ix-dir__h2">Recently listed</h2>
            @if ($browse['newest']->isEmpty())
                <div class="ix-empty">
                    <h2>No business is listed yet</h2>
                    <p>Businesses appear here once they choose to be listed and say what they do.@auth If you run one, open its profile and choose who can find it.@endauth</p>
                </div>
            @else
                <ul class="ix-dir__results is-grid">
                    @foreach ($browse['newest'] as $team)
                        @include('businesses._card', ['team' => $team, 'why' => []])
                    @endforeach
                </ul>
                <p class="ix-vgap"><a class="ix-btn" href="{{ route('businesses.index', ['all' => 1, 'sort' => 'newest']) }}">See every listed business</a></p>
            @endif
        </section>
    @endif
</x-app-layout>
