<aside class="ix-side" :class="{ 'is-open': nav }" aria-label="Primary">
    <a href="{{ route('dashboard') }}" class="ix-brand" aria-label="InfoDot home">
        <picture>
            <source srcset="{{ asset('img/logo_white.png') }}" media="(prefers-color-scheme: dark)">
            <img src="{{ asset('img/logo.png') }}" alt="InfoDot" width="62" height="26">
        </picture>
    </a>

    <nav class="ix-nav">
        <div class="ix-nav__group">Work</div>
        <a href="{{ route('dashboard') }}" class="ix-nav__link" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
            <span class="material-symbols-rounded" aria-hidden="true">home</span>Home
        </a>
        <livewire:platform-link platform="files" label="Files" icon="folder" />
        <livewire:platform-link platform="docs" label="Documents" icon="description" />

        <div class="ix-nav__group">Knowledge</div>
        <a href="{{ route('questions') }}" class="ix-nav__link" @if(request()->routeIs('questions*', 'seek')) aria-current="page" @endif>
            <span class="material-symbols-rounded" aria-hidden="true">forum</span>Questions
        </a>
        <a href="{{ route('solutions') }}" class="ix-nav__link" @if(request()->routeIs('solutions*', 'add')) aria-current="page" @endif>
            <span class="material-symbols-rounded" aria-hidden="true">checklist</span>Solutions
        </a>

        <div class="ix-nav__group">Ecosystem</div>
        <a href="{{ route('ecosystem.widget') }}" class="ix-nav__link" @if(request()->routeIs('ecosystem.widget', 'subservices.*')) aria-current="page" @endif>
            <span class="material-symbols-rounded" aria-hidden="true">apps</span>Platforms
        </a>
        <a href="{{ route('analytics.dashboard') }}" class="ix-nav__link" @if(request()->routeIs('analytics.*')) aria-current="page" @endif>
            <span class="material-symbols-rounded" aria-hidden="true">monitoring</span>Analytics
        </a>

        @if (\App\Support\NavSection::administersTeam(auth()->user()))
            <div class="ix-nav__group">Administration</div>
            <a href="{{ route('teams.show', auth()->user()->currentTeam->id) }}" class="ix-nav__link" @if(request()->routeIs('teams.*')) aria-current="page" @endif>
                <span class="material-symbols-rounded" aria-hidden="true">groups</span>Team and members
            </a>
            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                <a href="{{ route('api-tokens.index') }}" class="ix-nav__link" @if(request()->routeIs('api-tokens.*')) aria-current="page" @endif>
                    <span class="material-symbols-rounded" aria-hidden="true">key</span>API tokens
                </a>
            @endif
        @endif
    </nav>

    <div class="ix-side__foot">
        <b>{{ auth()->user()->currentTeam->name ?? 'Personal workspace' }}</b>
        <span>{{ auth()->user()->name }}</span>
    </div>
</aside>
