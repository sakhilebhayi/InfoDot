{{-- Specialist badges: one per topic, from work other people accepted or a moderator verified. Not points, not a score, no overall tier. --}}
@php($notice = session('badges_notice'))
<section class="mb-8 ix-ach" aria-labelledby="profile-badges-heading" id="profile-badges">
    <h2 id="profile-badges-heading" class="ix-ach__title">Specialist badges</h2>

    <p class="ix-ach__note">
        @if ($isOwner)
            A badge says that other people accepted your answers, or a moderator verified your solutions, in one topic. There is no overall level. A badge is taken back if the work behind it is hidden or un-accepted, and it never gives you extra say or changes how your answers are shown.
        @else
            Topics where other people accepted {{ $user->name }}'s answers or a moderator verified their solutions. It is not a score.
        @endif
    </p>

    @if ($isOwner && $notice)
        <p class="ix-ach__notice" role="status">{{ $notice }}</p>
    @endif

    @if ($badges === [])
        <p class="ix-ach__empty">No badge yet. A badge appears in a topic once at least {{ collect(config('achievements.badges.levels'))->min('min_work') }} of your answers there were accepted by other people or your solutions were verified.</p>
    @else
        <ul class="ix-ach__areas" aria-label="{{ $isOwner ? 'Your specialist badges' : 'Specialist badges' }}">
            @foreach ($badges as $b)
                <li data-badge="{{ $b['level'] }}" data-badge-topic="{{ $b['slug'] }}">
                    <a href="{{ route('topics.show', ['topic' => $b['slug']]) }}">{{ $b['topic'] }}: {{ $b['level_name'] }}</a>
                    <span class="ix-hint">{{ $b['work_count'] }} {{ \Illuminate\Support\Str::plural('piece', $b['work_count']) }} of accepted or verified work</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($isOwner)
        <form method="POST" action="{{ route('profile.badges-visibility') }}" class="ix-ach__share">
            @csrf
            <input type="hidden" name="badges_visible" value="0">
            <label class="ix-pts__check" for="badges-visible">
                <input type="checkbox" id="badges-visible" name="badges_visible" value="1" aria-describedby="badges-visible-hint badges-visible-state" @checked($user->showsBadges())>
                <span>Show my specialist badges to other people</span>
            </label>
            <p class="ix-hint" id="badges-visible-hint">When on, the badge for a question's topic appears beside your name on your answers there, and all your badges are listed on your profile. When off, nobody else sees any of them. This is separate from sharing your achievements above.</p>
            @error('badges_visible')
                <p class="ix-error" role="alert">Choose on or off, then save.</p>
            @enderror
            <p class="ix-hint" id="badges-visible-state">Right now this is <strong>{{ $user->showsBadges() ? 'on' : 'off' }}</strong>.</p>
            <button type="submit" class="ix-btn">Save</button>
        </form>
    @endif
</section>
