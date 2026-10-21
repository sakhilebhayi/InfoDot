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

    <section class="mb-8 ix-contrib" aria-labelledby="profile-contribution">
        <h2 id="profile-contribution" style="margin: 0 0 10px; font-family: var(--ix-font); font-weight: 700; font-size: 20px;">Contribution</h2>

        <div class="ix-readouts">
            <div class="ix-readout" style="border-left: 0;">
                <x-ix.figure :value="$stats['questions']" />
                <span class="block text-xs" style="color: var(--ix-text-3);">Questions asked</span>
            </div>
            <div class="ix-readout">
                <x-ix.figure :value="$stats['answers']" />
                <span class="block text-xs" style="color: var(--ix-text-3);">Answers given</span>
            </div>
            <div class="ix-readout">
                <x-ix.figure :value="$stats['accepted']" />
                <span class="block text-xs" style="color: var(--ix-text-3);">Accepted as the solution</span>
            </div>
        </div>
        @unless ($isOwner)
            <p class="ix-contrib__note">Counts cover public questions and answers only.</p>
        @endunless

        @if ($isOwner)
            <div class="ix-points" aria-labelledby="points-heading">
                <h3 id="points-heading">Dot Points</h3>
                @if ($points)
                    <p class="ix-points__balance"><strong>{{ number_format($points['balance']) }}</strong> you can redeem</p>
                    <p class="ix-points__note">
                        Points have no cash value and cannot be transferred. Each award lapses 12 months after you earn it.
                        @if (! empty($points['expiring_soon']))
                            <strong>{{ number_format($points['expiring_soon']['points']) }} lapse by {{ \Illuminate\Support\Carbon::parse($points['expiring_soon']['first_at'])->format('j M Y') }}.</strong>
                        @elseif (! empty($points['next_expiry']))
                            The next ones lapse on {{ \Illuminate\Support\Carbon::parse($points['next_expiry'])->format('j M Y') }}.
                        @endif
                    </p>
                    <p class="ix-points__note">{{ number_format($points['total_earned']) }} earned in total.</p>

                    @if (! empty($points['history']))
                        <table class="ix-points__history">
                            <caption class="sr-only">Your recent points history</caption>
                            <thead><tr><th scope="col">Date</th><th scope="col">What</th><th scope="col">Points</th></tr></thead>
                            <tbody>
                                @foreach (array_slice($points['history'], 0, 10) as $row)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($row['at'])->format('j M Y') }}</td>
                                        <td>
                                            {{ $row['label'] }}
                                            @if ($row['type'] === 'expire') <span class="ix-points__tag">lapsed</span> @endif
                                            @if ($row['type'] === 'reverse') <span class="ix-points__tag">taken back</span> @endif
                                            @if ($row['type'] === 'redeem') <span class="ix-points__tag">spent</span> @endif
                                        </td>
                                        <td>{{ $row['points'] > 0 ? '+' : '' }}{{ $row['points'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="ix-points__note">Nothing earned yet. Points come from answers other people find useful, and from solutions that are accepted or verified.</p>
                    @endif
                @elseif ($pointsConfigured)
                    <p class="ix-points__note">Your points are unavailable right now. Nothing is lost; they will show here when the points service is back.</p>
                @else
                    <p class="ix-points__note">Points are not switched on yet.</p>
                @endif
                <p class="ix-points__note"><a href="{{ route('points') }}" class="ix-points__link">Redeem points and see your redemptions</a></p>
            </div>
        @endif
    </section>

    @if ($isOwner && $moderation->isNotEmpty())
        <section class="mb-8 ix-modstatus" aria-labelledby="profile-moderation">
            <h2 id="profile-moderation" style="margin: 0 0 10px; font-family: var(--ix-font); font-weight: 700; font-size: 20px;">Moderation of your content</h2>
            <p class="ix-points__note">A moderator hid these. Open one to read why and, if you disagree, appeal it once.</p>
            <ul class="ix-modstatus__list">
                @foreach ($moderation as $row)
                    <li>
                        <p class="ix-modstatus__title">
                            <span class="ix-chip {{ $row['hidden'] ? 'ix-chip--open' : 'ix-chip--done' }}">{{ $row['hidden'] ? 'Hidden' : 'Restored' }}</span>
                            {{ $row['kind'] }}: <a href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                        </p>
                        @if ($row['hidden'] && filled($row['reason']))
                            <p class="ix-points__note">Reason: {{ $row['reason'] }}</p>
                        @endif
                        <p class="ix-points__note">
                            @if ($row['appeal'] === null)
                                No appeal sent yet.
                            @elseif ($row['appeal']->isOpen())
                                Appeal sent {{ $row['appeal']->created_at->diffForHumans() }}, waiting for a moderator.
                            @elseif ($row['appeal']->status === 'upheld')
                                Appeal upheld {{ $row['appeal']->decided_at?->format('j M Y') }}.
                            @else
                                Appeal denied {{ $row['appeal']->decided_at?->format('j M Y') }}@if (filled($row['appeal']->decision_note)): {{ $row['appeal']->decision_note }}@endif. This is final.
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

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
