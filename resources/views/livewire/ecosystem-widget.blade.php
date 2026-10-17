@php
    $platforms = collect(config('ecosystem.platforms', []));
    $total = $platforms->count();
    $live = $platforms->filter(fn ($p) => ! empty($p['active']))->count();
@endphp
<div>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Platforms</h1>
            <p class="ix-lede">Open a live Dot platform with your current sign-in. Platforms that are not live yet are listed so you can see what is coming.</p>
        </div>
        <div class="ix-readouts">
            <div class="ix-readout"><span class="ix-readout__label">Live</span><x-ix.figure :value="$live" /></div>
            <div class="ix-readout"><span class="ix-readout__label">Coming</span><x-ix.figure :value="$total - $live" /></div>
        </div>
    </div>

    <div class="ix-stack">
        @foreach($this->groups as $groupName => $members)
            @if(count($members))
                <section class="ix-panel ix-panel--clip" aria-labelledby="grp-{{ Str::slug($groupName) }}">
                    <h2 class="ix-panel__head" id="grp-{{ Str::slug($groupName) }}">
                        <span>{{ $groupName }}</span>
                        <span class="ix-hint" style="margin:0">{{ count($members) }}</span>
                    </h2>
                    <div class="ix-dir">
                        @foreach(collect($members)->sortByDesc(fn ($p) => ! empty($p['active'])) as $key => $platform)
                            @php($isLive = ! empty($platform['active']))
                            <button type="button"
                                    class="ix-dir__item {{ $launching === $key ? 'is-launching' : '' }}"
                                    @if($isLive)
                                        wire:click="launch('{{ $key }}')"
                                        wire:loading.attr="disabled"
                                    @else
                                        disabled
                                    @endif
                                    title="{{ $isLive ? 'Open '.$platform['name'] : $platform['name'].' is not live yet' }}">
                                <span class="ix-dir__icon"><span class="material-symbols-rounded" aria-hidden="true">{{ $platform['icon'] }}</span></span>
                                <span class="ix-dir__text">
                                    <span class="ix-dir__name">{{ $platform['name'] }}</span>
                                    <span class="ix-dir__state">
                                        @if($launching === $key) Opening&hellip;
                                        @else {{ $isLive ? 'Live' : 'Not live yet' }}
                                        @endif
                                    </span>
                                </span>
                                @if($isLive)<span class="material-symbols-rounded ix-dir__go" aria-hidden="true">arrow_outward</span>@endif
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    <p class="ix-hint" style="margin-top:16px">Opening a platform signs you in there with a short-lived token from your current session.</p>
</div>
