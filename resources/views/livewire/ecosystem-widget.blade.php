@php
    $platforms = collect(\App\Support\Platforms::visible())->except('infodot');
    $total = $platforms->count();
    $live = $platforms->filter(fn ($p) => ! empty($p['active']))->count();
@endphp
<div>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Platforms</h1>
            <p class="ix-lede">The register of every Dot platform. Live ones open already signed in with your current session; the rest are listed so you can see what is coming.</p>
        </div>
        <div class="ix-readouts">
            <div class="ix-readout"><span class="ix-readout__label">Live</span><x-ix.figure :value="$live" /></div>
            <div class="ix-readout"><span class="ix-readout__label">Coming</span><x-ix.figure :value="$total - $live" /></div>
        </div>
    </div>

    <div class="ix-register">
        <nav class="ix-register__index" aria-label="Platform areas">
            @foreach($this->groups as $groupName => $group)
                @if(count($group['members']))
                    <a href="#reg-{{ Str::slug($groupName) }}" data-tone="{{ $group['tone'] }}" style="--tone: var(--ix-{{ $group['tone'] }})">
                        <span class="ix-bays__code">{{ $group['code'] }}</span>
                        <span class="ix-register__idxname">{{ $groupName }}</span>
                        <span class="ix-register__idxn">{{ collect($group['members'])->filter(fn ($p) => ! empty($p['active']))->count() }}/{{ count($group['members']) }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="ix-register__body">
            @foreach($this->groups as $groupName => $group)
                @if(count($group['members']))
                    <section id="reg-{{ Str::slug($groupName) }}" class="ix-reg" data-tone="{{ $group['tone'] }}" style="--tone: var(--ix-{{ $group['tone'] }})" aria-labelledby="grp-{{ Str::slug($groupName) }}">
                        <h2 class="ix-reg__head" id="grp-{{ Str::slug($groupName) }}">
                            <span class="ix-bays__code">{{ $group['code'] }}</span>
                            <span>{{ $groupName }}</span>
                        </h2>
                        <ul class="ix-reg__rows">
                            @foreach(collect($group['members'])->sortByDesc(fn ($p) => ! empty($p['active'])) as $key => $platform)
                                @php($isLive = ! empty($platform['active']))
                                @php($host = parse_url($platform['url'], PHP_URL_HOST) ?: $platform['url'])
                                @php($hasLogo = file_exists(resource_path('img/apps/'.$key.'.png')))
                                <li>
                                    <button type="button"
                                            class="ix-regrow {{ $isLive ? '' : 'is-soon' }} {{ $launching === $key ? 'is-launching' : '' }}"
                                            @if($isLive) wire:click="launch('{{ $key }}')" wire:loading.attr="disabled" @else disabled @endif
                                            title="{{ $isLive ? 'Open '.$platform['name'] : $platform['name'].' is not live yet' }}">
                                        <span class="ix-regrow__logo">
                                            @if($hasLogo)
                                                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/apps/'.$key.'.png') }}" alt="" width="96" height="64" loading="lazy">
                                            @else
                                                <span class="material-symbols-rounded" aria-hidden="true">{{ $platform['icon'] }}</span>
                                            @endif
                                        </span>
                                        <span class="ix-regrow__name">{{ $platform['name'] }}<small>{{ $host }}</small></span>
                                        <span class="ix-regrow__state">
                                            @if($launching === $key) Opening&hellip;
                                            @elseif($isLive) <span class="ix-regrow__live">Live</span>
                                            @else <span class="ix-regrow__soon">Not live yet</span>
                                            @endif
                                        </span>
                                        <span class="ix-regrow__go" aria-hidden="true">
                                            @if($isLive)Open <span class="material-symbols-rounded">arrow_outward</span>@endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>
    </div>

    <p class="ix-hint" style="margin-top:16px">Opening a platform signs you in there with a short-lived token from your current session.</p>
</div>
