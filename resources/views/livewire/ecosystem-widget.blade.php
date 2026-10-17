@php
    $platforms = collect(config('ecosystem.platforms', []));
    $total = $platforms->count();
    $live = $platforms->filter(fn ($p) => ! empty($p['active']))->count();
@endphp
<div>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Platforms</h1>
            <p class="ix-lede">Open any live Dot platform with your current session. Platforms that are not live yet are listed so you can see what is coming.</p>
        </div>
    </div>

    <div class="ix-state-bar">
        <div class="ix-state-bar__verdict">
            <span class="ix-lamp ix-lamp--ok" aria-hidden="true"></span>
            {{ $live }} of {{ $total }} platforms are live
        </div>
        <div class="ix-readouts">
            <div class="ix-readout"><span class="ix-readout__label">Live</span><x-ix.figure :value="$live" /></div>
            <div class="ix-readout"><span class="ix-readout__label">Not live</span><x-ix.figure :value="$total - $live" /></div>
        </div>
    </div>

    <div class="ix-stack">
        @foreach($this->groups as $groupName => $members)
            @if(count($members))
                <section class="ix-panel ix-panel--clip" aria-labelledby="grp-{{ Str::slug($groupName) }}">
                    <h2 class="ix-panel__head" id="grp-{{ Str::slug($groupName) }}">
                        <span>{{ $groupName }}</span>
                        <x-ix.figure :value="count($members)" />
                    </h2>
                    <div class="ix-bays">
                        @foreach(collect($members)->sortByDesc(fn ($p) => ! empty($p['active'])) as $key => $platform)
                            @php($isLive = ! empty($platform['active']))
                            <button type="button"
                                    class="ix-bay {{ $isLive ? 'ix-bay--live' : '' }} {{ $launching === $key ? 'is-launching' : '' }}"
                                    @if($isLive)
                                        wire:click="launch('{{ $key }}')"
                                        wire:loading.attr="disabled"
                                    @else
                                        disabled
                                    @endif
                                    title="{{ $isLive ? 'Open '.$platform['name'] : $platform['name'].' is not live yet' }}">
                                <span class="material-symbols-rounded" aria-hidden="true">{{ $platform['icon'] }}</span>
                                <span class="ix-bay__text">
                                    <span class="ix-bay__name">{{ $platform['name'] }}</span>
                                    <span class="ix-bay__state">
                                        @if($launching === $key)
                                            Opening&hellip;
                                        @else
                                            {{ $isLive ? 'Live' : 'Not live yet' }}
                                        @endif
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    <p class="ix-hint" style="margin-top:16px">Single sign-on: opening a platform hands it a short-lived token from your current session.</p>
</div>
