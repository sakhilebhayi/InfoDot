<header class="ix-top">
    <div class="ix-top__inner">
        <a href="{{ route('questions') }}" class="ix-brand" aria-label="InfoDot home">
            <img src="{{ asset('img/logo_white.png') }}" alt="InfoDot">
        </a>

        <nav class="ix-tabs" aria-label="Primary">
            <a href="{{ route('questions') }}" class="ix-tab" @if(request()->routeIs('questions*', 'seek')) aria-current="page" @endif>Questions</a>
            <a href="{{ route('solutions') }}" class="ix-tab" @if(request()->routeIs('solutions*', 'add')) aria-current="page" @endif>Solutions</a>
            <a href="{{ route('ecosystem.widget') }}" class="ix-tab" @if(request()->routeIs('ecosystem.widget')) aria-current="page" @endif>Platforms</a>
            <a href="{{ route('analytics.dashboard') }}" class="ix-tab" @if(request()->routeIs('analytics.*')) aria-current="page" @endif>Analytics</a>
        </nav>

        <div class="ix-top__actions">
            <livewire:dot-switcher />

            @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && Auth::user()->currentTeam)
                <div class="ix-pop" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="ix-round ix-round--wide hidden sm:inline-flex" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                        <span class="material-symbols-rounded" aria-hidden="true">groups</span>{{ __('Team') }}
                    </button>
                    <div class="ix-pop__panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                        <div class="ix-pop__head">{{ Auth::user()->currentTeam->name }}</div>
                        <a class="ix-pop__item" href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
                            <span class="material-symbols-rounded" aria-hidden="true">settings</span>{{ __('Team settings') }}
                        </a>
                        @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                            <a class="ix-pop__item" href="{{ route('teams.create') }}">
                                <span class="material-symbols-rounded" aria-hidden="true">add</span>{{ __('Create new team') }}
                            </a>
                        @endcan

                        @if (Auth::user()->allTeams()->count() > 1)
                            <div class="ix-pop__rule"></div>
                            <div class="ix-pop__head">{{ __('Switch team') }}</div>
                            @foreach (Auth::user()->allTeams() as $team)
                                <x-jet-switchable-team :team="$team" />
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            <div class="ix-pop" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="ix-round" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="{{ __('Account menu') }}">
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
                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                        <a class="ix-pop__item" href="{{ route('api-tokens.index') }}">
                            <span class="material-symbols-rounded" aria-hidden="true">key</span>{{ __('API tokens') }}
                        </a>
                    @endif
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
