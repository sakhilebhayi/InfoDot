<x-app-layout :title="$team->name.' inbox'">
    <header class="ix-inhead">
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
        <a href="{{ route('business.show', $team) }}" class="ix-btn"><span class="material-symbols-rounded" aria-hidden="true">storefront</span>Business page</a>
    </header>

    @if ($stats)
        @php
            $s = $stats['by_status'];
            $waiting = ($s['new'] ?? 0);
            $active = collect($s)->except(['resolved', 'closed'])->sum();
            $hours = $stats['median_first_response_hours'];
        @endphp
        <dl class="ix-inboxstats" aria-label="The inbox in numbers">
            <div @class(['is-alert' => $waiting > 0])><dt>Not yet answered</dt><dd>{{ $waiting }}</dd></div>
            <div><dt>Not yet resolved</dt><dd>{{ $active }}</dd></div>
            <div><dt>Received, last 30 days</dt><dd>{{ $stats['last_30_days'] }}</dd></div>
            <div><dt>Typical first reply, last 90 days</dt><dd>{{ match (true) { $hours === null => 'None yet', $hours < 1 => max(1, (int) round($hours * 60)).' min', default => $hours.' h' } }}</dd></div>
        </dl>
        @if ($stats['answered'] > 0)<p class="ix-hint ix-inboxstats__note">The typical first reply is the middle value of {{ $stats['answered'] }} answered {{ \Illuminate\Support\Str::plural('enquiry', $stats['answered']) }}, measured from when each arrived.</p>@endif
    @endif

    <section class="ix-inbox" aria-labelledby="list-h">
        <div class="ix-inbox__bar">
            <h2 id="list-h" class="ix-inbox__h2">Conversations</h2>
            <form method="get" action="{{ route('business.inbox', $team) }}" class="ix-inbox__filters" aria-label="Filter the inbox">
                <label class="ix-dir__filter"><span>Status</span>
                    <select class="ix-select" name="status">
                        <option value="active" @selected($f['status'] === 'active')>Not resolved</option>
                        <option value="all" @selected($f['status'] === 'all')>All</option>
                        @foreach (\App\Models\Enquiry::STATUSES as $value => $label)<option value="{{ $value }}" @selected($f['status'] === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                @if ($manager)
                    <label class="ix-dir__filter"><span>Who has it</span>
                        <select class="ix-select" name="who">
                            <option value="">Anyone</option>
                            <option value="mine" @selected(($f['who'] ?? null) === 'mine')>Me</option>
                            <option value="unassigned" @selected(($f['who'] ?? null) === 'unassigned')>Nobody yet</option>
                        </select>
                    </label>
                @endif
                <label class="ix-dir__filter ix-inbox__search"><span>Subject</span><input class="ix-input" type="search" name="q" maxlength="80" value="{{ $f['q'] ?? '' }}"></label>
                <button type="submit" class="ix-btn">Show</button>
            </form>
        </div>

        @if ($enquiries->isEmpty())
            <div class="ix-inbox__empty">
                <span class="material-symbols-rounded" aria-hidden="true">inbox</span>
                @if ($f['status'] === 'active' && empty($f['who']) && empty($f['q']))
                    <h3>No open enquiries</h3>
                    <p>When someone messages {{ $team->name }} from its page, it arrives here and the owner and administrators are told.</p>
                    <a href="{{ route('business.show', $team) }}" class="ix-btn">See the page people message from</a>
                @else
                    <h3>Nothing matches these filters</h3>
                    <p>Try another status, or clear the subject.</p>
                    <a href="{{ route('business.inbox', $team) }}" class="ix-btn">Show open enquiries</a>
                @endif
            </div>
        @else
            <ul class="ix-inbox__rows" role="list">
                @foreach ($enquiries as $e)
                    @include('inbox._row', [
                        'href' => route('business.inbox.show', [$team, $e]),
                        'subject' => $e->subject,
                        'who' => ($e->customer?->name ?? 'Former InfoDot user').($e->customerTeam ? ' ('.$e->customerTeam->name.')' : ''),
                        'meta' => array_values(array_filter([
                            mb_strtolower($e->categoryLabel()),
                            $e->assignee ? 'with '.$e->assignee->name : null,
                            $e->follow_up_on ? 'follow up '.$e->follow_up_on->format('j M') : null,
                        ])),
                        'plate' => null,
                        'initial' => $e->customer?->name,
                        'unread' => $e->unreadForHandler($reads[$e->id] ?? null),
                        'unreadLabel' => 'Unread',
                        'status' => \App\Models\Enquiry::STATUSES[$e->status] ?? $e->status,
                        'statusTone' => $e->status === 'new' ? 'open' : (in_array($e->status, ['resolved', 'closed'], true) ? 'done' : null),
                        'preview' => isset($latest[$e->id]) ? $latest[$e->id]->body : null,
                        'previewMine' => isset($latest[$e->id]) && $latest[$e->id]->kind === \App\Models\EnquiryMessage::BUSINESS,
                        'previewPrefix' => 'Replied:',
                        'updated' => $e->updated_at,
                        'of' => $e,
                    ])
                @endforeach
            </ul>
        @endif
    </section>
    @include('partials.pager', ['pager' => $enquiries, 'label' => 'Enquiries'])
</x-app-layout>
