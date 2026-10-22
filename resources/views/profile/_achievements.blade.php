{{-- Achievements: milestones from contribution. Deliberately its own section, apart from the Dot Points panel: they are not points and cannot be redeemed. --}}
@php($notice = session('achievements_notice'))
<section class="mb-8 ix-ach" aria-labelledby="profile-achievements-heading" id="profile-achievements">
    <h2 id="profile-achievements-heading" class="ix-ach__title">Achievements</h2>

    <p class="ix-ach__note">
        @if ($isOwner)
            Milestones from the work you have shared. They are not points, cannot be redeemed, and are not a score. Only public work counts, and a milestone is listed only while the work behind it still stands.
        @else
            Milestones from public work {{ $user->name }} has shared. They are not points and not a score.
        @endif
    </p>

    @if ($isOwner && $notice)
        <p class="ix-ach__notice" role="status">{{ $notice }}</p>
    @endif

    @if ($achievements['earned']->isEmpty())
        <p class="ix-ach__empty">{{ $isOwner ? 'No milestones yet. Ask a public question or answer someone else\'s to reach the first one.' : $user->name.' has not reached a milestone yet.' }}</p>
    @else
        <ul class="ix-ach__list" aria-label="{{ $isOwner ? 'Milestones you have reached' : 'Milestones reached' }}">
            @foreach ($achievements['earned'] as $a)
                <li class="ix-ach__item" data-achievement="{{ $a['key'] }}">
                    <p class="ix-ach__name">{{ $a['name'] }}</p>
                    <p class="ix-ach__meaning">{{ $a['meaning'] }}</p>
                    <p class="ix-ach__date">Reached {{ $a['earned_at']->format('j M Y') }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($isOwner && ! empty($achievements['next']))
        <h3 class="ix-ach__sub" id="ach-next">Next milestones</h3>
        <ul class="ix-ach__next" aria-labelledby="ach-next">
            @foreach ($achievements['next'] as $n)
                <li>
                    <p class="ix-ach__name">{{ $n['name'] }}</p>
                    <p class="ix-ach__progress-text">{{ $n['current'] }} of {{ $n['target'] }} {{ $n['label'] }}</p>
                    <progress class="ix-ach__bar" max="{{ $n['target'] }}" value="{{ $n['current'] }}" aria-label="{{ $n['name'] }}: {{ $n['current'] }} of {{ $n['target'] }} {{ $n['label'] }}"></progress>
                </li>
            @endforeach
        </ul>
    @endif

    @php($portfolio = $achievements['portfolio'])
    @if ($portfolio['accepted_total'] + $portfolio['verified_total'] > 0)
        <h3 class="ix-ach__sub" id="ach-portfolio">Public contributions</h3>
        @if ($isOwner)
            <p class="ix-ach__note">{{ $achievements['shared'] ? 'Other people see this list on your profile.' : 'Other people cannot see this list. It appears on your profile for them if you share it below.' }}</p>
        @endif
        <div class="ix-ach__portfolio">
            @if ($portfolio['accepted']->isNotEmpty())
                <div>
                    <h4 class="ix-ach__kind">Answers accepted as the solution</h4>
                    <ul>
                        @foreach ($portfolio['accepted'] as $row)
                            <li><a href="{{ $row['url'] }}">{{ $row['title'] }}</a></li>
                        @endforeach
                    </ul>
                    @if ($portfolio['accepted_total'] > $portfolio['accepted']->count())
                        <p class="ix-hint">Latest {{ $portfolio['accepted']->count() }} of {{ $portfolio['accepted_total'] }}.</p>
                    @endif
                </div>
            @endif
            @if ($portfolio['verified']->isNotEmpty())
                <div>
                    <h4 class="ix-ach__kind">Verified solutions</h4>
                    <ul>
                        @foreach ($portfolio['verified'] as $row)
                            <li><a href="{{ $row['url'] }}">{{ $row['title'] }}</a></li>
                        @endforeach
                    </ul>
                    @if ($portfolio['verified_total'] > $portfolio['verified']->count())
                        <p class="ix-hint">Latest {{ $portfolio['verified']->count() }} of {{ $portfolio['verified_total'] }}.</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($isOwner)
        <form method="POST" action="{{ route('profile.achievements-visibility') }}" class="ix-ach__share">
            @csrf
            <input type="hidden" name="achievements_public" value="0">
            <label class="ix-pts__check" for="ach-public">
                <input type="checkbox" id="ach-public" name="achievements_public" value="1" aria-describedby="ach-public-hint ach-public-state" @checked($achievements['shared'])>
                <span>Show my achievements and public contributions to other people</span>
            </label>
            <p class="ix-hint" id="ach-public-hint">Shows the milestones above and titles of your accepted answers and verified solutions, nothing else: no points, no private or company questions, no moderation history.</p>
            @error('achievements_public')
                <p class="ix-error" role="alert">Choose on or off, then save.</p>
            @enderror
            <p class="ix-hint" id="ach-public-state">Right now this is <strong>{{ $achievements['shared'] ? 'on' : 'off' }}</strong>.</p>
            <button type="submit" class="ix-btn">Save</button>
        </form>
    @endif
</section>
