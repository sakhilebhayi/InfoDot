@use('App\Services\Platforms\CompanyCatalogue')
@php
    $rows = $this->rows;
    $matches = $this->matches;
    $groups = $this->groups;
    $team = CompanyCatalogue::teamOf(auth()->user());
    $isCompany = $this->isCompany;
    $filtered = $q !== '' || $category !== '' || $state !== '';
    $heldCount = $rows->where('held', true)->count();
    $recommendations = $filtered ? collect() : $this->recommendations;

    // The colour of a state's chip: in use, something the company can do, something that needs attention, or plain.
    $chip = fn (array $row) => match (true) {
        in_array($row['state'], ['included', 'active', 'trial'], true) => 'ix-chip--done',
        in_array($row['state'], CompanyCatalogue::SWITCHABLE, true) => 'ix-chip--info',
        in_array($row['state'], ['payment_required', 'subscription_expired', 'suspended', 'activation_pending'], true) => 'ix-chip--open',
        default => '',
    };
@endphp
<div class="ix-cat">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Platforms</h1>
            <p class="ix-lede">
                @if ($isCompany)
                    What {{ $team->name }} has today, and what else it can switch on. A platform your company has opens already signed in.
                @else
                    Your personal workspace has the free core. The other platforms are for companies.
                @endif
            </p>
        </div>
        <div class="ix-readouts">
            <div class="ix-readout"><span class="ix-readout__label">{{ $isCompany ? 'Your company has' : 'You have' }}</span><x-ix.figure :value="$heldCount" /></div>
            <div class="ix-readout"><span class="ix-readout__label">In the catalogue</span><x-ix.figure :value="$rows->count()" /></div>
        </div>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @unless ($isCompany)
        <div class="ix-cat__personal">
            <p>Platforms beyond the free core are switched on by a company, for the people who work in it. Create a company, or switch to one you belong to from the menu at the top left.</p>
            <a href="{{ route('teams.create') }}" class="ix-btn">Create a company</a>
        </div>
    @endunless

    @if ($recommendations->isNotEmpty())
        <section class="ix-cat__suggest" aria-labelledby="cat-suggest">
            <h2 id="cat-suggest">Suggested for {{ $team->name }}</h2>
            <ul>
                @foreach ($recommendations as $suggestion)
                    @php($row = $suggestion['row'])
                    <li data-recommendation="{{ $row['key'] }}">
                        <span class="ix-regrow__logo ix-catrow__logo">
                            @if (file_exists(resource_path('img/apps/'.$row['key'].'.png')))
                                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/apps/'.$row['key'].'.png') }}" alt="" width="96" height="64" loading="lazy">
                            @endif
                        </span>
                        <p>
                            <a href="{{ route('platforms.show', ['platform' => $row['key']]) }}">{{ $row['name'] }}</a>
                            <span>{{ $suggestion['reason'] }}</span>
                        </p>
                        <div class="ix-actions">
                            <a href="{{ route('platforms.show', ['platform' => $row['key']]) }}" class="ix-btn ix-btn--sm">See details</a>
                            <form method="POST" action="{{ route('platforms.recommendation.dismiss', ['platform' => $row['key']]) }}">
                                @csrf
                                <input type="hidden" name="company" value="{{ $team->uuid }}">
                                <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm">Not for us<span class="ix-sr">: do not suggest {{ $row['name'] }} again</span></button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form class="ix-cat__tools" method="GET" action="{{ route('ecosystem.widget') }}" role="search" wire:submit="$refresh">
        <label class="ix-cat__search">
            <span class="ix-sr">Search platforms by name or by what you need</span>
            <span class="material-symbols-rounded" aria-hidden="true">search</span>
            <input type="search" name="q" class="ix-input" wire:model.live.debounce.250ms="q" placeholder="Search by name or need, such as invoices or leave" autocomplete="off">
        </label>
        <label>
            <span class="ix-sr">Category</span>
            <select name="category" class="ix-select" wire:model.live="category">
                <option value="">All categories</option>
                @foreach ($this->categories as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="ix-sr">State</span>
            <select name="state" class="ix-select" wire:model.live="state">
                <option value="">Any state</option>
                @foreach ($this->states as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <noscript><button type="submit" class="ix-btn">Filter</button></noscript>
    </form>

    <p class="ix-cat__count" role="status">
        @if ($filtered)
            {{ $matches->count() }} of {{ $rows->count() }} platforms match.
            <button type="button" class="ix-cat__clear" wire:click="clearFilters">Clear the search and filters</button>
        @else
            <span class="ix-sr">{{ $rows->count() }} platforms.</span>
        @endif
    </p>

    @if ($groups === [])
        <div class="ix-empty">
            <h2>No platform matches that</h2>
            <p>Try a different word, or clear the filters to see the whole catalogue.</p>
            <button type="button" class="ix-btn" wire:click="clearFilters">Show every platform</button>
        </div>
    @else
        <div class="ix-register">
            <nav class="ix-register__index" aria-label="Sections of the catalogue">
                @foreach ($groups as $group)
                    <a href="#reg-{{ $group['id'] }}" data-tone="{{ $group['tone'] }}" style="--tone: var(--ix-{{ $group['tone'] }})">
                        <span class="ix-bays__code">{{ $group['code'] }}</span>
                        <span class="ix-register__idxname">{{ $group['name'] }}</span>
                        <span class="ix-register__idxn">{{ $group['rows']->count() }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="ix-register__body">
                @foreach ($groups as $group)
                    <section id="reg-{{ $group['id'] }}" class="ix-reg" data-tone="{{ $group['tone'] }}" style="--tone: var(--ix-{{ $group['tone'] }})" aria-labelledby="grp-{{ $group['id'] }}" wire:key="grp-{{ $group['id'] }}">
                        <h2 class="ix-reg__head" id="grp-{{ $group['id'] }}">
                            <span class="ix-bays__code">{{ $group['code'] }}</span>
                            <span>{{ $group['name'] }}</span>
                        </h2>
                        <ul class="ix-reg__rows">
                            @foreach ($group['rows'] as $key => $row)
                                <li class="ix-catrow" data-platform="{{ $key }}" data-state="{{ $row['state'] }}" wire:key="row-{{ $key }}">
                                    <a class="ix-catrow__what" href="{{ route('platforms.show', ['platform' => $key]) }}">
                                        <span class="ix-regrow__logo ix-catrow__logo">
                                            @if (file_exists(resource_path('img/apps/'.$key.'.png')))
                                                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/apps/'.$key.'.png') }}" alt="" width="96" height="64" loading="lazy">
                                            @else
                                                <span class="material-symbols-rounded" aria-hidden="true">{{ config('ecosystem.platforms.'.$key.'.icon', 'apps') }}</span>
                                            @endif
                                        </span>
                                        <span class="ix-catrow__name">{{ $row['name'] }}<small>{{ $row['tagline'] }}</small></span>
                                    </a>
                                    <span class="ix-catrow__state">
                                        <span class="ix-chip {{ $chip($row) }}">{{ $row['label'] }}</span>
                                        @if ($row['price'] && ! $row['held'])<small>{{ $row['price'] }}</small>@endif
                                    </span>
                                    <span class="ix-catrow__act">
                                        @if ($row['action'] === 'open')
                                            <button type="button" class="ix-btn ix-btn--primary ix-btn--sm" data-platform-action="open" wire:click="launch('{{ $key }}')" wire:loading.attr="disabled" wire:target="launch('{{ $key }}')" aria-label="Open {{ $row['name'] }}">
                                                {{ $launching === $key ? 'Opening…' : 'Open' }}
                                            </button>
                                        @elseif ($row['action'] === 'switch_on')
                                            <a class="ix-btn ix-btn--sm" data-platform-action="switch_on" href="{{ route('platforms.switch-on.confirm', ['platform' => $key]) }}" aria-label="Switch on {{ $row['name'] }}">Switch on</a>
                                        @elseif ($row['action'] === 'finance')
                                            <button type="button" class="ix-btn ix-btn--sm" data-platform-action="finance" wire:click="launch('finance')" aria-label="Open Dot.Finance to pay for {{ $row['name'] }}">Open Dot.Finance</button>
                                        @else
                                            <a class="ix-btn ix-btn--ghost ix-btn--sm" data-platform-action="details" href="{{ route('platforms.show', ['platform' => $key]) }}" aria-label="See details of {{ $row['name'] }}">See details</a>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
