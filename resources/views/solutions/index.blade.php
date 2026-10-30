<x-app-layout>
    @php
        $team = auth()->user()->currentTeam;
        $mins = fn ($s) => $s->duration_type === 'infinite' ? null : (int) $s->duration * ['hours' => 60, 'days' => 1440, 'weeks' => 10080, 'months' => 43200, 'years' => 525600][$s->duration_type];
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Solutions</h1>
            <p class="ix-lede">Procedures written down as steps, so the same problem is only solved once. Open one and follow it.</p>
        </div>
        <a href="{{ route('add') }}" class="ix-btn ix-btn--primary">
            <span class="material-symbols-rounded" aria-hidden="true">add</span>Write a solution
        </a>
    </div>

    @include('topics._filter', ['route' => route('solutions')])

    @if ($solutions->isNotEmpty())
        <div x-data="{ q: '', sort: 'new' }">
            <div class="ix-board__bar" style="margin-bottom:16px">
                <label class="ix-board__find" style="max-width:420px">
                    <span class="ix-sr">Filter solutions</span>
                    <span class="material-symbols-rounded" aria-hidden="true">filter_list</span>
                    <input type="search" x-model="q" placeholder="Filter by title, tag or words">
                </label>
                <label class="ix-sortby">
                    <span>Sort</span>
                    <select x-model="sort" class="ix-input ix-select">
                        <option value="new">Newest</option>
                        <option value="steps">Most steps</option>
                        <option value="quick">Quickest</option>
                    </select>
                </label>
            </div>

            <ul class="ix-library">
                @foreach ($solutions as $solution)
                    @php($m = $mins($solution))
                    <li class="ix-sol"
                        data-new="{{ $solution->created_at->timestamp }}" data-steps="{{ (int) $solution->steps }}" data-quick="{{ $m ?? 999999999 }}"
                        :style="'order:' + (sort === 'steps' ? -{{ (int) $solution->steps }} : sort === 'quick' ? {{ $m ?? 999999999 }} : -{{ $solution->created_at->timestamp }})"
                        x-show="q === '' || @js(strtolower($solution->solution_title.' '.$solution->solution_description.' '.$solution->tags)).includes(q.toLowerCase())">
                        <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-sol__link">
                            <span class="ix-sol__steps">
                                <span class="ix-stencil-num">{{ str_pad((string) min((int) $solution->steps, 99), 2, '0', STR_PAD_LEFT) }}</span>
                                <small>{{ Str::plural('step', (int) $solution->steps) }}</small>
                            </span>
                            <span class="ix-sol__text">
                                <span class="ix-sol__title">{{ $solution->solution_title }}</span>
                                <span class="ix-sol__ex">{{ Str::limit($solution->solution_description, 150) }}</span>
                            </span>
                        </a>
                        <p class="ix-sol__meta">
                            <span class="ix-ref">{{ \App\Support\Reference::for($solution) }}</span>
                            @if ($solution->isVerified())<span class="ix-chip ix-chip--done">Verified by moderator</span>@endif
                            <span>
                                @if ($solution->duration_type === 'infinite') Time unknown
                                @else {{ $solution->duration }} {{ Str::plural(rtrim($solution->duration_type, 's'), $solution->duration) }}
                                @endif
                            </span>
                            <span>{{ $solution->user->name ?? 'Former member' }}@if ($solution->user?->isOfficialAccount()) <span class="ix-chip ix-chip--info">Official</span>@endif</span>
                            <span>{{ $solution->comments_count }} {{ Str::plural('reply', $solution->comments_count) }}</span>
                            <span>{{ $solution->isPublic() ? 'Everyone' : 'Your company' }}</span>
                        </p>
                        @include('topics._chips', ['item' => $solution])
                    </li>
                @endforeach
            </ul>
            @if ($limited)<p class="ix-hint">Showing the latest 200. Pick a topic above to narrow the list.</p>@endif

            <section class="ix-ideas ix-ideas--wide" aria-label="Ideas">
                <h2 class="ix-ideas__head">Worth writing down at a {{ strtolower($industry['label']) }} business</h2>
                <ul class="ix-ideas__list">
                    @foreach ($industry['solutions'] as $idea)
                        <li><a class="ix-idea" href="{{ route('add') }}">{{ $idea }}</a></li>
                    @endforeach
                </ul>
            </section>
        </div>
    @elseif ($filterSlug !== '')
        <section class="ix-empty">
            <h2>{{ $filterSlug === 'none' ? 'Every solution you can see has a topic' : ($activeTopic ? 'No solutions under '.$activeTopic->name.' yet' : 'There is no topic with that address') }}</h2>
            <p>{{ $activeTopic ? 'Nothing has been written under this topic, or nothing you can see.' : 'Pick a topic from the list, or clear the filter to see every solution.' }}</p>
            <a href="{{ route('solutions') }}" class="ix-btn">Show every solution</a>
            @if ($activeTopic)<a href="{{ route('add', ['topic' => $activeTopic->slug]) }}" class="ix-btn ix-btn--primary">Write the first one</a>@endif
        </section>
    @else
        <section class="ix-empty ix-empty--start">
            <h2>{{ $team?->name ?? 'Your team' }} has no solutions yet</h2>
            <p>When you work something out that others will hit again, write it as steps. Here is what {{ strtolower($industry['label']) }} teams usually write down first.</p>
            <ul class="ix-ideas__list">
                @foreach ($industry['solutions'] as $idea)
                    <li><a class="ix-idea" href="{{ route('add') }}">{{ $idea }}</a></li>
                @endforeach
            </ul>
            <a href="{{ route('add') }}" class="ix-btn ix-btn--primary">Write the first solution</a>
        </section>
    @endif
</x-app-layout>
