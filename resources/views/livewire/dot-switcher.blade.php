@php
    $groups = $this->groups;
    $count = collect($groups)->sum(fn ($group) => count($group['members']));
    $team = auth()->user()?->currentTeam;
    $isCompany = $team && ! $team->personal_team;
@endphp
<div x-data="{ open: @entangle('open'), q: '' }" @click.outside="open = false" @keydown.escape="if (open) { open = false; $el.querySelector('button').focus() }" class="ix-yardwrap">
    <button type="button" class="ix-btn" @click="open = !open; if (open) $nextTick(() => $refs.f.focus())" :aria-expanded="open" aria-haspopup="true" aria-controls="ix-yardmap" title="Switch Dot platform">
        <span class="material-symbols-rounded" aria-hidden="true">apps</span>
        <span class="hidden sm:inline">Dot Apps</span>
    </button>

    <div id="ix-yardmap" class="ix-yardmap" x-show="open" x-cloak x-transition.opacity.duration.100ms role="dialog" aria-label="Dot platforms">
        <div class="ix-yardmap__in">
            <div class="ix-yardmap__head">
                <div>
                    <p class="ix-yardmap__title">Dot Apps</p>
                    <p class="ix-yardmap__sub">
                        {{ $count === 1 ? 'The one platform' : 'The '.$count.' platforms' }} {{ $isCompany ? $team->name.' has and you can open' : 'in your personal workspace' }}. Each opens already signed in.
                        <a href="{{ route('ecosystem.widget') }}" class="ix-yardmap__all" data-all-platforms>See all platforms</a>
                    </p>
                </div>
                <label class="ix-yardmap__filter">
                    <span class="ix-sr">Filter platforms</span>
                    <span class="material-symbols-rounded" aria-hidden="true">search</span>
                    <input type="search" x-ref="f" x-model="q" placeholder="Filter platforms" autocomplete="off">
                </label>
            </div>

            <div class="ix-yardmap__groups">
                @foreach($groups as $group)
                    <section class="ix-bays" data-tone="{{ $group['tone'] }}" style="--tone: var(--ix-{{ $group['tone'] }})"
                             x-data="{ names: @js(collect($group['members'])->map(fn ($row) => strtolower($row['name']))->values()->all()) }"
                             x-show="q === '' || names.some(n => n.includes(q.toLowerCase()))">
                        <h3 class="ix-bays__head"><span class="ix-bays__code">{{ $group['code'] }}</span><span class="ix-bays__name">{{ $group['name'] }}</span></h3>
                        <div class="ix-bays__grid">
                            @foreach($group['members'] as $key => $row)
                                <button type="button" class="ix-bay" wire:click="switchTo('{{ $key }}')"
                                        x-show="q === '' || @js(strtolower($row['name'])).includes(q.toLowerCase())"
                                        title="Open {{ $row['name'] }}">
                                    <span class="ix-bay__logo">
                                        @if(file_exists(resource_path('img/apps/'.$key.'.png')))
                                            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/apps/'.$key.'.png') }}" alt="" width="64" height="52" loading="lazy">
                                        @else
                                            <span class="material-symbols-rounded" aria-hidden="true">{{ config('ecosystem.platforms.'.$key.'.icon', 'apps') }}</span>
                                        @endif
                                    </span>
                                    <span class="ix-bay__name">{{ $row['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>
