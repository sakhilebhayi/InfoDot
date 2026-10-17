<x-app-layout>
    @if ($solutions->isNotEmpty())
        <div class="ix-list">
            @foreach ($solutions as $solution)
                <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-row">
                    <span class="ix-lead ix-lead--solution">
                        <span class="material-symbols-rounded" aria-hidden="true">format_list_numbered</span>
                    </span>
                    <span>
                        <h2 class="ix-row__title">{{ $solution->solution_title }}</h2>
                        <p class="ix-row__excerpt">{{ $solution->solution_description }}</p>
                        <p class="ix-row__by">
                            {{ $solution->user->name ?? 'Former member' }} &middot; {{ $solution->created_at->diffForHumans() }}
                            @if ($solution->duration_type !== 'infinite')
                                &middot; takes {{ $solution->duration }} {{ Str::plural(rtrim($solution->duration_type, 's'), $solution->duration) }}
                            @endif
                        </p>
                    </span>
                    <span class="ix-row__figs">
                        <span class="ix-count"><x-ix.figure :value="$solution->steps ?? 0" /><small>{{ Str::plural('step', $solution->steps ?? 0) }}</small></span>
                        <span class="ix-count"><x-ix.figure :value="$solution->comments_count" /><small>{{ Str::plural('reply', $solution->comments_count) }}</small></span>
                        <span class="ix-go" aria-hidden="true"><span class="material-symbols-rounded">chevron_right</span></span>
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
