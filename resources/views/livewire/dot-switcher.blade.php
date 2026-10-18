@php
    $all = $this->platforms;
    // code, area colour variable, members
    $groups = [
        'Workspace' => ['WORK', 'knowledge', ['files', 'docs', 'forms', 'sheet', 'projects', 'tasks', 'hr', 'plug']],
        'AI & Automation' => ['AUTO', 'analytics', ['agents', 'analytics']],
        'Community & Engagement' => ['COMM', 'platforms', ['pulse', 'engage', 'press']],
        'Commerce & Finance' => ['FINA', 'files', ['finance', 'emall', 'auction', 'billing', 'charts']],
        'Services & Learning' => ['LEARN', 'documents', ['ehail', 'tutor', 'design']],
        'Infrastructure' => ['INFRA', 'people', ['central', 'notify']],
        'Industry Verticals' => ['VERT', 'knowledge', ['mines', 'farms']],
    ];
    $liveCount = collect($all)->filter(fn ($p, $k) => $k !== 'infodot' && ! empty($p['active']))->count();
    $total = collect($all)->except('infodot')->count();
@endphp
<div x-data="{ open: @entangle('open'), q: '' }" @click.outside="open = false" @keydown.escape="open = false" class="ix-yardwrap">
    <button type="button" class="ix-btn" @click="open = !open; if (open) $nextTick(() => $refs.f.focus())" :aria-expanded="open" aria-haspopup="true" aria-controls="ix-yardmap" title="Switch Dot platform">
        <span class="material-symbols-rounded" aria-hidden="true">apps</span>
        <span class="hidden sm:inline">Dot Apps</span>
    </button>

    <div id="ix-yardmap" class="ix-yardmap" x-show="open" x-cloak x-transition.opacity.duration.100ms role="dialog" aria-label="Dot platforms">
        <div class="ix-yardmap__in">
            <div class="ix-yardmap__head">
                <div>
                    <p class="ix-yardmap__title">The Dot ecosystem</p>
                    <p class="ix-yardmap__sub">{{ $liveCount }} of {{ $total }} platforms are live. Each opens already signed in.</p>
                </div>
                <label class="ix-yardmap__filter">
                    <span class="ix-sr">Filter platforms</span>
                    <span class="material-symbols-rounded" aria-hidden="true">search</span>
                    <input type="search" x-ref="f" x-model="q" placeholder="Filter platforms" autocomplete="off">
                </label>
            </div>

            <div class="ix-yardmap__groups">
                @foreach($groups as $name => [$code, $tone, $keys])
                    @php($members = collect($keys)->filter(fn ($k) => isset($all[$k]))->sortByDesc(fn ($k) => ! empty($all[$k]['active']))->values())
                    @if($members->isNotEmpty())
                        <section class="ix-bays" data-tone="{{ $tone }}" style="--tone: var(--ix-{{ $tone }})"
                                 x-data="{ names: @js($members->map(fn ($k) => strtolower($all[$k]['name']))->all()) }"
                                 x-show="q === '' || names.some(n => n.includes(q.toLowerCase()))">
                            <h3 class="ix-bays__head"><span class="ix-bays__code">{{ $code }}</span><span class="ix-bays__name">{{ $name }}</span></h3>
                            <div class="ix-bays__grid">
                                @foreach($members as $key)
                                    @php($platform = $all[$key])
                                    @php($live = (bool) ($platform['active'] ?? false))
                                    @php($hasLogo = file_exists(resource_path('img/apps/'.$key.'.png')))
                                    <button type="button"
                                            class="ix-bay{{ $live ? '' : ' is-soon' }}"
                                            @if($live) wire:click="switchTo('{{ $key }}')" @else disabled @endif
                                            x-show="q === '' || @js(strtolower($platform['name'])).includes(q.toLowerCase())"
                                            title="{{ $live ? $platform['name'] : $platform['name'].' is not live yet' }}">
                                        <span class="ix-bay__logo">
                                            @if($hasLogo)
                                                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/apps/'.$key.'.png') }}" alt="" width="64" height="52" loading="lazy">
                                            @else
                                                <span class="material-symbols-rounded" aria-hidden="true">{{ $platform['icon'] }}</span>
                                            @endif
                                        </span>
                                        <span class="ix-bay__name">{{ $platform['name'] }}</span>
                                        @unless($live)<span class="ix-bay__soon">Soon</span>@endunless
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
