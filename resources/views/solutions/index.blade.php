<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Solutions</h1>
            <p class="ix-lede">Step-by-step answers your team has written down, so the same problem is only solved once.</p>
        </div>
        <a href="{{ route('add') }}" class="ix-btn ix-btn--primary">
            <span class="material-symbols-rounded" aria-hidden="true">add</span>Add a solution
        </a>
    </div>

    @if ($solutions->isNotEmpty())
        <div class="ix-list">
            @foreach ($solutions as $solution)
                <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-row">
                    <span>
                        <span class="ix-fig" style="font-size:16px">
                            @if ($solution->duration_type === 'infinite')
                                &mdash;
                            @else
                                <x-ix.figure :value="$solution->duration" />
                            @endif
                        </span>
                        <span style="display:block;font-size:12px;color:var(--ix-text-3)">{{ $solution->duration_type === 'infinite' ? 'time unknown' : Str::plural(rtrim($solution->duration_type, 's'), $solution->duration) }}</span>
                    </span>
                    <span>
                        <h2 class="ix-row__title">{{ $solution->solution_title }}</h2>
                        <p class="ix-row__excerpt">{{ $solution->solution_description }}</p>
                        <p class="ix-row__by">{{ $solution->user->name ?? 'Former member' }} &middot; {{ $solution->created_at->diffForHumans() }}</p>
                    </span>
                    <span class="ix-row__figs">
                        <span class="ix-count"><x-ix.figure :value="$solution->steps ?? 0" /><small>{{ Str::plural('step', $solution->steps ?? 0) }}</small></span>
                        <span class="ix-count"><x-ix.figure :value="$solution->comments_count" /><small>{{ Str::plural('reply', $solution->comments_count) }}</small></span>
                        <span class="ix-count"><x-ix.figure :value="$solution->likes_count" /><small>{{ Str::plural('like', $solution->likes_count) }}</small></span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <section class="ix-empty">
            <h2>No solutions yet</h2>
            <p>When you work something out that others will hit again, write it down as steps. It shows up here and in search.</p>
            <a href="{{ route('add') }}" class="ix-btn ix-btn--primary">Add the first solution</a>
        </section>
    @endif
</x-app-layout>
