@php
    $company = Auth::user()->currentTeam;
@endphp
<header class="ix-top">
    <div class="ix-top__inner">
        <div class="ix-pop" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" class="ix-owner" style="background:none;border-top:0;border-bottom:0;border-left:0;color:inherit;cursor:pointer;text-align:left" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="Company menu">
                <span class="ix-owner__code">{{ $company?->code ?? 'INFO' }}</span>
                <span class="ix-owner__name">{{ $company?->name ?? 'Personal workspace' }}<small>{{ ($company?->personal_team ?? true) ? 'Personal workspace' : 'Company' }}</small></span>
                <span class="material-symbols-rounded" aria-hidden="true" style="opacity:.7">expand_more</span>
            </button>
            <div class="ix-pop__panel" style="left:0;right:auto" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                @if ($company)
                    <div class="ix-pop__head">{{ $company->name }} &middot; {{ $company->code }}</div>
                    <a class="ix-pop__item" href="{{ route('people') }}">
                        <span class="material-symbols-rounded" aria-hidden="true">groups</span>{{ __('People') }}
                    </a>
                    @if (\App\Support\NavSection::administersTeam(Auth::user()))
                        <a class="ix-pop__item" href="{{ route('teams.show', $company->id) }}">
                            <span class="material-symbols-rounded" aria-hidden="true">settings</span>{{ __('Team settings') }}
                        </a>
                        @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                            <a class="ix-pop__item" href="{{ route('api-tokens.index') }}">
                                <span class="material-symbols-rounded" aria-hidden="true">key</span>{{ __('API tokens') }}
                            </a>
                        @endif
                    @endif
                @endif
                @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                    <a class="ix-pop__item" href="{{ route('teams.create') }}">
                        <span class="material-symbols-rounded" aria-hidden="true">add</span>{{ __('Create new team') }}
                    </a>
                @endcan

                @if (Auth::user()->allTeams()->count() > 1)
                    <div class="ix-pop__rule"></div>
                    <div class="ix-pop__head">{{ __('Switch company') }}</div>
                    @foreach (Auth::user()->allTeams() as $team)
                        <x-jet-switchable-team :team="$team" />
                    @endforeach
                @endif
            </div>
        </div>

        <nav class="ix-tabs" aria-label="Primary">
            <a href="{{ route('dashboard') }}" class="ix-tab" data-area="home" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Home</a>
            <a href="{{ route('questions') }}" class="ix-tab" data-area="knowledge" @if(request()->routeIs('questions*', 'seek', 'solutions*', 'add')) aria-current="page" @endif>Knowledge</a>
            <livewire:platform-link platform="files" label="Files" icon="folder" variant="tab" />
            <livewire:platform-link platform="docs" label="Documents" icon="description" variant="tab" />
            <a href="{{ route('ecosystem.widget') }}" class="ix-tab" data-area="platforms" @if(request()->routeIs('ecosystem.widget', 'subservices.*')) aria-current="page" @endif>Platforms</a>
            <a href="{{ route('people') }}" class="ix-tab" data-area="people" @if(request()->routeIs('people', 'teams.*', 'api-tokens.*')) aria-current="page" @endif>People</a>
            <a href="{{ route('analytics.dashboard') }}" class="ix-tab" data-area="analytics" @if(request()->routeIs('analytics.*')) aria-current="page" @endif>Analytics</a>
        </nav>

        <div class="ix-top__search">
            <livewire:search />
        </div>

        <div class="ix-top__actions">
            <livewire:dot-switcher />

            <div class="ix-pop" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="ix-avatar" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="{{ __('Account menu') }}">
                    @if (Auth::user()->profile_photo_path != null)
                        <img src="{{ Auth::user()->profile_photo_url }}" alt="">
                    @else
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    @endif
                </button>
                <div class="ix-pop__panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                    <div class="ix-pop__head">{{ Auth::user()->email }}</div>
                    <a class="ix-pop__item" href="{{ route('profile.show', ['id' => Auth::id()]) }}">
                        <span class="material-symbols-rounded" aria-hidden="true">person</span>{{ __('Profile') }}
                    </a>
                    <a class="ix-pop__item" href="{{ route('profile.edit') }}">
                        <span class="material-symbols-rounded" aria-hidden="true">tune</span>{{ __('Settings') }}
                    </a>
                    <div class="ix-pop__rule"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="ix-pop__item">
                            <span class="material-symbols-rounded" aria-hidden="true">logout</span>{{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
