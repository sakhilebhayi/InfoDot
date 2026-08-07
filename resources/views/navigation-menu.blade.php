<nav class="fixed top-0 left-72 right-0 z-40 h-20 border-b border-[#434656]/20 bg-[#0b1326]/85 text-[#dae2fd] backdrop-blur-xl" style="font-family:'Inter',sans-serif;">
    <div class="h-full px-6 lg:px-10">
        <div class="flex h-full items-center justify-between gap-4">
            <div class="flex min-w-0 flex-1 items-center gap-4 lg:gap-6">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-bold tracking-tight text-[#b6c4ff]" style="font-family:'Manrope',sans-serif;">Workspace Explorer</h2>
                    <p class="truncate text-[11px] uppercase tracking-[0.14em] text-[#8d90a2]">Command Center</p>
                </div>
                <div class="hidden w-full max-w-xl lg:block">
                    <livewire:search />
                </div>
            </div>

            <div class="flex items-center gap-2 lg:gap-4">
                <livewire:dot-switcher />

                @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && Auth::user()->currentTeam)
                    <div class="relative hidden sm:block">
                        <x-jet-dropdown align="right" width="60">
                            <x-slot name="trigger">
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center rounded-xl border border-[#434656]/50 bg-[#131b2e]/85 px-3 py-2 text-sm font-medium text-[#dae2fd] transition hover:border-[#2962ff]/50 hover:bg-[#1a2438] hover:text-[#b6c4ff] focus:outline-none">
                                        {{ __('Manage Team') }}
                                        <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </span>
                            </x-slot>

                            <x-slot name="content">
                                <div class="w-60">
                                    <div class="block px-4 py-2.5 text-xs uppercase tracking-wide text-[#8d90a2]">
                                        {{ __('Manage Team') }}
                                    </div>

                                    <x-jet-dropdown-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
                                        {{ __('Team Settings') }}
                                    </x-jet-dropdown-link>

                                    @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                                        <x-jet-dropdown-link href="{{ route('teams.create') }}">
                                            {{ __('Create New Team') }}
                                        </x-jet-dropdown-link>
                                    @endcan

                                    <div class="border-t border-[#434656]/40"></div>

                                    <div class="block px-4 py-2.5 text-xs uppercase tracking-wide text-[#8d90a2]">
                                        {{ __('Switch Teams') }}
                                    </div>

                                    @foreach (Auth::user()->allTeams() as $team)
                                        <x-jet-switchable-team :team="$team" />
                                    @endforeach
                                </div>
                            </x-slot>
                        </x-jet-dropdown>
                    </div>
                @endif

                <div class="relative">
                    <x-jet-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            @if (Auth::user()->profile_photo_path != null)
                                <button class="flex rounded-full border-2 border-[#2962ff]/25 p-0.5 text-sm transition hover:border-[#2962ff]/60 focus:outline-none focus:border-[#2962ff]/70">
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            @else
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center rounded-full border-2 border-[#2962ff]/25 p-0.5 text-sm transition hover:border-[#2962ff]/60 focus:outline-none focus:border-[#2962ff]/70">
                                        <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->avatar() }}" alt="{{ Auth::user()->name }}" />
                                    </button>
                                </span>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            <x-jet-dropdown-link href="{{ route('profile.show', ['id' => Auth::id()]) }}">
                                {{ __('Profile') }}
                            </x-jet-dropdown-link>

                            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                <x-jet-dropdown-link href="{{ route('api-tokens.index') }}">
                                    {{ __('API Tokens') }}
                                </x-jet-dropdown-link>
                            @endif

                            <div class="border-t border-[#434656]/40"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-jet-dropdown-link href="{{ route('logout') }}"
                                                     onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-jet-dropdown-link>
                            </form>
                        </x-slot>
                    </x-jet-dropdown>
                </div>
            </div>
        </div>
    </div>
</nav>
