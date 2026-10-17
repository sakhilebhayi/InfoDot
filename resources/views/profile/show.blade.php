<x-app-layout>
    @php
        abort_unless($user, 404);

        $isOwner = Auth::id() === $user->id;
        $limit = 25;
        $questions = $user->questions->sortByDesc('created_at')->values();
        $solutions = $user->solutions->sortByDesc('created_at')->values();
        $associateCount = $user->associates->count();
    @endphp

    <div class="ix-pagehead" style="align-items: center;">
        <div class="flex min-w-0 items-center gap-4">
            <span class="ix-avatar" style="width: 56px; height: 56px; font-size: 22px;">
                @if ($user->profile_photo_path)
                    <img src="{{ $user->profile_photo_url }}" alt="">
                @else
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                @endif
            </span>

            <div class="min-w-0">
                <h1 class="ix-h1 truncate">{{ $user->name }}</h1>
                @if ($user->created_at)
                    <p class="ix-lede" style="margin-top: 2px;">Member since {{ $user->created_at->format('j M Y') }}</p>
                @endif
            </div>
        </div>

        <div>
            @if (! $isOwner)
                <livewire:associates :model="$user" :user="$user" :key="$user->id" />
            @else
                <a href="{{ route('profile.edit') }}" class="ix-btn">
                    <span class="material-symbols-rounded" aria-hidden="true">edit</span>Edit profile
                </a>
            @endif
        </div>
    </div>

    <div class="ix-state-bar">
        <div class="ix-readouts">
            <div class="ix-readout" style="border-left: 0;">
                <x-ix.figure :value="$associateCount" />
                <span class="block text-xs" style="color: var(--ix-text-3);">Associates</span>
            </div>
            <div class="ix-readout">
                <x-ix.figure :value="$questions->count()" />
                <span class="block text-xs" style="color: var(--ix-text-3);">Questions</span>
            </div>
            <div class="ix-readout">
                <x-ix.figure :value="$solutions->count()" />
                <span class="block text-xs" style="color: var(--ix-text-3);">Solutions</span>
            </div>
        </div>
    </div>

    <section class="mb-8" aria-labelledby="profile-questions">
        <h2 id="profile-questions" style="margin: 0 0 10px; font-family: var(--ix-font); font-weight: 700; font-size: 20px;">Questions</h2>

        @if ($questions->isEmpty())
            <div class="ix-panel ix-panel__body" style="color: var(--ix-text-2);">
                {{ $isOwner ? 'You have not asked any questions yet.' : $user->name.' has not asked any questions yet.' }}
                @if ($isOwner)
                    <a href="{{ route('seek') }}" style="color: var(--ix-text); text-decoration: underline;">Ask a question</a>
                @endif
            </div>
        @else
            <div class="ix-list">
                @foreach ($questions->take($limit) as $question)
                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-row">
                        <span class="ix-lead {{ (int) $question->status === 1 ? 'ix-lead--done' : '' }}"><span class="material-symbols-rounded" aria-hidden="true">{{ (int) $question->status === 1 ? 'check' : 'question_mark' }}</span><span class="ix-sr">{{ (int) $question->status === 1 ? 'Solved' : 'Open' }}</span></span>
                        <div class="min-w-0">
                            <h3 class="ix-row__title">{{ $question->question }}</h3>
                            @if ($question->description)
                                <p class="ix-row__excerpt">{{ $question->description }}</p>
                            @endif
                            <p class="ix-row__by">Asked {{ $question->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            @if ($questions->count() > $limit)
                <p class="ix-hint">Showing the latest {{ $limit }} of {{ $questions->count() }} questions.</p>
            @endif
        @endif
    </section>

    <section aria-labelledby="profile-solutions">
        <h2 id="profile-solutions" style="margin: 0 0 10px; font-family: var(--ix-font); font-weight: 700; font-size: 20px;">Solutions</h2>

        @if ($solutions->isEmpty())
            <div class="ix-panel ix-panel__body" style="color: var(--ix-text-2);">
                {{ $isOwner ? 'You have not added any solutions yet.' : $user->name.' has not added any solutions yet.' }}
                @if ($isOwner)
                    <a href="{{ route('add') }}" style="color: var(--ix-text); text-decoration: underline;">Add a solution</a>
                @endif
            </div>
        @else
            <div class="ix-list">
                @foreach ($solutions->take($limit) as $solution)
                    <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-row">
                        <span class="ix-lead ix-lead--solution"><span class="material-symbols-rounded" aria-hidden="true">format_list_numbered</span><span class="ix-sr">Solution</span></span>
                        <div class="min-w-0">
                            <h3 class="ix-row__title">{{ $solution->solution_title }}</h3>
                            @if ($solution->solution_description)
                                <p class="ix-row__excerpt">{{ $solution->solution_description }}</p>
                            @endif
                            <p class="ix-row__by">
                                Added {{ $solution->created_at->diffForHumans() }}
                                @if (! empty($solution->tags))
                                    &middot; {{ collect(explode(',', $solution->tags))->map(fn ($tag) => trim($tag))->filter()->implode(', ') }}
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
            @if ($solutions->count() > $limit)
                <p class="ix-hint">Showing the latest {{ $limit }} of {{ $solutions->count() }} solutions.</p>
            @endif
        @endif
    </section>
</x-app-layout>
