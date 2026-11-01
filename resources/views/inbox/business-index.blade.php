<x-app-layout :title="$team->name.' inbox'">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $team->name }} inbox</h1>
            <p class="ix-lede">
                @if ($manager)
                    Enquiries people sent the business through InfoDot. You see all of them; members see only the ones you give them.
                @else
                    The enquiries given to you. The owner and administrators see the whole inbox.
                @endif
            </p>
        </div>
        <a href="{{ route('business.show', $team) }}" class="ix-btn ix-btn--ghost">Business page</a>
    </div>

    @if ($stats)
        @php
            $s = $stats['by_status'];
            $waiting = ($s['new'] ?? 0);
            $active = collect($s)->except(['resolved', 'closed'])->sum();
        @endphp
        <dl class="ix-inboxstats" aria-label="The inbox in numbers">
            <div><dt>Not yet answered</dt><dd>{{ $waiting }}</dd></div>
            <div><dt>Not yet resolved</dt><dd>{{ $active }}</dd></div>
            <div><dt>Received, last 30 days</dt><dd>{{ $stats['last_30_days'] }}</dd></div>
            <div><dt>Typical first reply, last 90 days</dt><dd>{{ match (true) { $stats['median_first_response_hours'] === null => '—', $stats['median_first_response_hours'] < 1 => max(1, (int) round($stats['median_first_response_hours'] * 60)).' min', default => $stats['median_first_response_hours'].' h' } }}</dd></div>
        </dl>
        @if ($stats['answered'] > 0)<p class="ix-hint">The typical first reply is the middle value of {{ $stats['answered'] }} answered {{ \Illuminate\Support\Str::plural('enquiry', $stats['answered']) }}, measured from when each arrived.</p>@endif
    @endif

    <form method="get" action="{{ route('business.inbox', $team) }}" class="ix-dir__filters ix-vgap" aria-label="Filter the inbox">
        <label class="ix-dir__filter"><span>Status</span>
            <select class="ix-input" name="status">
                <option value="active" @selected($f['status'] === 'active')>Not resolved</option>
                <option value="all" @selected($f['status'] === 'all')>All</option>
                @foreach (\App\Models\Enquiry::STATUSES as $value => $label)<option value="{{ $value }}" @selected($f['status'] === $value)>{{ $label }}</option>@endforeach
            </select>
        </label>
        @if ($manager)
            <label class="ix-dir__filter"><span>Who has it</span>
                <select class="ix-input" name="who">
                    <option value="">Anyone</option>
                    <option value="mine" @selected(($f['who'] ?? null) === 'mine')>Me</option>
                    <option value="unassigned" @selected(($f['who'] ?? null) === 'unassigned')>Nobody yet</option>
                </select>
            </label>
        @endif
        <label class="ix-dir__filter"><span>Subject</span><input class="ix-input" name="q" maxlength="80" value="{{ $f['q'] ?? '' }}"></label>
        <button type="submit" class="ix-btn">Show</button>
    </form>

    @if ($enquiries->isEmpty())
        <div class="ix-empty">
            <h2>Nothing here</h2>
            <p>@if ($f['status'] === 'active' && empty($f['who']) && empty($f['q']))No open enquiries. When someone messages {{ $team->name }} from its page, it arrives here and the owner and administrators are told.@else Nothing matches these filters.@endif</p>
        </div>
    @else
        <ul class="ix-threads">
            @foreach ($enquiries as $e)
                @php
                    $unread = $e->unreadForHandler($reads[$e->id] ?? null);
                @endphp
                <li @class(['is-unread' => $unread])>
                    <div class="ix-threads__main">
                        <a href="{{ route('business.inbox.show', [$team, $e]) }}" class="ix-threads__subject">{{ $e->subject }}</a>
                        <span class="ix-threads__who">{{ $e->customer?->name ?? 'Former InfoDot user' }}, {{ mb_strtolower($e->categoryLabel()) }}@if ($e->assignee) — with {{ $e->assignee->name }}@endif @if ($e->follow_up_on) — follow up {{ $e->follow_up_on->format('j M') }}@endif</span>
                    </div>
                    <span class="ix-threads__state">
                        @if ($unread)<span class="ix-chip ix-chip--info">Unread</span>@endif
                        <span class="ix-chip @if($e->status === 'new') ix-chip--open @elseif(in_array($e->status, ['resolved', 'closed'], true)) ix-chip--done @endif">{{ \App\Models\Enquiry::STATUSES[$e->status] ?? $e->status }}</span>
                    </span>
                    <time class="ix-threads__when" datetime="{{ $e->updated_at->toIso8601String() }}">{{ $e->updated_at->diffForHumans() }}</time>
                </li>
            @endforeach
        </ul>
        @include('partials.pager', ['pager' => $enquiries, 'label' => 'Enquiries'])
    @endif
</x-app-layout>
