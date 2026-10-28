{{-- The moderators' desk views. `counts` is given by the queue, which has already counted what is waiting. --}}
<nav class="ix-mod__tabs" aria-label="Moderation views">
    <a href="{{ route('moderation', ['tab' => 'reports']) }}" @if ($current === 'reports') aria-current="page" @endif>Reports @isset($counts)<span class="ix-mod__n">{{ $counts['reports'] }} open</span>@endisset</a>
    <a href="{{ route('moderation', ['tab' => 'appeals']) }}" @if ($current === 'appeals') aria-current="page" @endif>Appeals @isset($counts)<span class="ix-mod__n">{{ $counts['appeals'] }} open</span>@endisset</a>
    <a href="{{ route('moderation', ['tab' => 'approvals']) }}" @if ($current === 'approvals') aria-current="page" @endif>Awaiting approval @isset($counts)<span class="ix-mod__n">{{ $counts['approvals'] }} waiting</span>@endisset</a>
    <a href="{{ route('moderation.library') }}" @if ($current === 'library') aria-current="page" @endif>Recommended</a>
    <a href="{{ route('moderation.topics') }}" @if ($current === 'topics') aria-current="page" @endif>Topics</a>
    <a href="{{ route('moderation.friction') }}" @if ($current === 'friction') aria-current="page" @endif>Where people struggle</a>
</nav>
