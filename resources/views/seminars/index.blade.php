<x-app-layout>
    @php($flash = session('seminar_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Seminars</h1>
            <p class="ix-lede">Free sessions run by people in the community. Anyone signed in can host one, and no level or ranking is needed. Each host brings their own meeting link, which you get when you register.</p>
        </div>
        <a class="ix-btn ix-btn--primary" href="{{ route('seminars.create') }}">Host a seminar</a>
    </div>

    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif

    <form class="ix-topicbar" action="{{ route('seminars') }}" method="GET" role="search" aria-label="Filter seminars by topic">
        <label for="seminar-topic">Topic</label>
        <select id="seminar-topic" name="topic" class="ix-input ix-select">
            <option value="">All topics</option>
            @foreach ($topics as $t)
                <option value="{{ $t->id }}" @selected($topicId === $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="ix-btn">Show</button>
        @if ($topicId)<a class="ix-topicbar__clear" href="{{ route('seminars') }}">Clear</a>@endif
    </form>

    @if ($mine->isNotEmpty())
        <section aria-labelledby="mine-h">
            <h2 class="ix-label" id="mine-h">You are hosting</h2>
            <ul class="ix-list">
                @foreach ($mine as $s)
                    <li style="padding: 12px 14px;">
                        <a href="{{ route('seminars.show', $s) }}"><strong>{{ $s->title }}</strong></a>
                        <span class="ix-chip {{ $s->isPublished() ? 'ix-chip--open' : '' }}">{{ $s->isPublished() ? 'Published' : 'Draft' }}</span>
                        <span class="ix-ref">{{ $s->starts_at->timezone(config('app.timezone'))->format('D j M, H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($upcoming->isEmpty())
        <section class="ix-empty">
            <h2>{{ $topicId ? 'No seminars on this topic yet' : 'No seminars scheduled yet' }}</h2>
            <p>Know something other business owners need? <a href="{{ route('seminars.create') }}" style="text-decoration: underline;">Host the first one</a>.</p>
        </section>
    @else
        <section aria-labelledby="upcoming-h">
            <h2 class="ix-label" id="upcoming-h">Coming up</h2>
            <ul class="ix-list">
                @foreach ($upcoming as $s)
                    @php($mineStatus = $registered[$s->id] ?? null)
                    <li style="padding: 14px;">
                        <a href="{{ route('seminars.show', $s) }}"><strong>{{ $s->title }}</strong></a>
                        @if ($mineStatus)<span class="ix-chip ix-chip--done">{{ $mineStatus === 'waitlist' ? 'Waiting list' : 'Registered' }}</span>@endif
                        <p class="ix-hint" style="margin: 4px 0;">{{ $s->summary }}</p>
                        <div class="ix-meta">
                            <span><b>{{ $s->starts_at->timezone(config('app.timezone'))->format('D j M Y, H:i') }}</b></span>
                            <span>{{ $s->duration_minutes }} min</span>
                            <span>Hosted by {{ $hosts[$s->host_id]->name ?? 'a member' }}</span>
                            <span>Free</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-app-layout>
