@php
    $all = $this->platforms;
    $groups = [
        'Workspace' => ['files', 'docs', 'forms', 'sheet', 'projects', 'tasks', 'hr', 'plug'],
        'AI & Automation' => ['agents', 'analytics', 'memory'],
        'Community & Engagement' => ['pulse', 'engage', 'press', 'dopemine'],
        'Commerce & Finance' => ['finance', 'emall', 'auction', 'billing', 'charts'],
        'Services & Learning' => ['ehail', 'tutor', 'design'],
        'Infrastructure' => ['central', 'notify'],
        'Industry Verticals' => ['mines', 'farms'],
    ];
@endphp
<div x-data="{ open: @entangle('open') }" @click.outside="open = false" @keydown.escape="open = false" class="ix-pop">
    <button type="button" class="ix-btn" @click="open = !open" :aria-expanded="open" aria-haspopup="true" title="Switch Dot platform">
        <span class="material-symbols-rounded" aria-hidden="true">apps</span>
        <span class="hidden sm:inline">Dot Apps</span>
    </button>

    <div class="ix-pop__panel ix-apps" x-show="open" x-cloak x-transition.opacity.duration.100ms>
        @foreach($groups as $name => $keys)
            @php($members = collect($keys)->filter(fn ($k) => isset($all[$k]))->sortByDesc(fn ($k) => ! empty($all[$k]['active'])))
            @if($members->isNotEmpty())
                <div class="ix-apps__group">{{ $name }}</div>
                <div class="ix-apps__grid">
                    @foreach($members as $key)
                        @php($platform = $all[$key])
                        @php($live = (bool) ($platform['active'] ?? false))
                        <button type="button"
                                class="ix-apps__item"
                                @if($live) wire:click="switchTo('{{ $key }}')" @else disabled @endif
                                title="{{ $live ? $platform['name'] : $platform['name'].' is not live yet' }}">
                            <span class="material-symbols-rounded" aria-hidden="true">{{ $platform['icon'] }}</span>
                            <span class="t">{{ $platform['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
</div>
