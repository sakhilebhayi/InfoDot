<header class="ix-top">
    <button type="button" class="ix-btn ix-btn--icon ix-btn--ghost ix-menu-btn" @click="nav = true" aria-label="Open menu">
        <span class="material-symbols-rounded" aria-hidden="true">menu</span>
    </button>

    <nav class="ix-crumbs" aria-label="Breadcrumb">
        @foreach (\App\Support\NavSection::crumbs() as [$label, $url])
            @if (! $loop->first)<span class="material-symbols-rounded sep" aria-hidden="true">chevron_right</span>@endif
            @if ($loop->last)
                <span class="here" aria-current="page">{{ $label }}</span>
            @elseif ($url)
                <a href="{{ $url }}">{{ $label }}</a>
            @else
                <span>{{ $label }}</span>
            @endif
        @endforeach
    </nav>

    <div class="ix-top__search">
        <livewire:search />
    </div>

    <div class="ix-top__actions">
        <livewire:dot-switcher />

        @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && Auth::user()->currentTeam)
            <div class="ix-pop hidden sm:block" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="ix-btn" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                    <span class="material-symbols-rounded" aria-hidden="true">groups</span>
                    <span class="max-w-[10rem] truncate">{{ Auth::user()->currentTeam->name }}</span>
                    <span class="material-symbols-rounded" aria-hidden="true">expand_more</span>
                </button>
                <div class="ix-pop__panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
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
</header>
