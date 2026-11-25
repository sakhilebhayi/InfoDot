<x-app-layout>
    @php($flash = session('notices_notice'))
    @php($new = $rows->filter(fn ($r) => $r['notice']->read_at === null))
    @php($earlier = $rows->filter(fn ($r) => $r['notice']->read_at !== null))

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Notifications</h1>
            <p class="ix-lede">What has happened to your own work and your companies: answers, mentions and improvements, bookings and enquiries, seminars, platforms, verification, and invitations to join a company. Put away what you have dealt with; it stays in the archive. Only you see this page.</p>
        </div>
        <div class="ix-notices__tools">
            @if ($unread > 0)
                <form method="POST" action="{{ route('notices.read-all') }}">
                    @csrf
                    <button type="submit" class="ix-btn">
                        <span class="material-symbols-rounded" aria-hidden="true">done_all</span>Mark all as read
                    </button>
                </form>
            @endif
            @if (! $archived && $earlier->isNotEmpty())
                <form method="POST" action="{{ route('notices.archive-read') }}">
                    @csrf
                    <button type="submit" class="ix-btn ix-btn--ghost">Put read ones away</button>
                </form>
            @endif
        </div>
    </div>

    <nav class="ix-notices__filters" aria-label="Notification categories">
        @php($all = collect($counts)->sum('total'))
        <a href="{{ route('notices', array_filter(['show' => $archived ? 'archived' : null])) }}" @if ($category === null) aria-current="page" @endif>All <span>{{ $all }}</span></a>
        @foreach (\App\Models\UserNotice::CATEGORIES as $key => $name)
            @continue(($counts[$key]['total'] ?? 0) === 0 && $category !== $key)
            <a href="{{ route('notices', array_filter(['category' => $key, 'show' => $archived ? 'archived' : null])) }}" @if ($category === $key) aria-current="page" @endif>{{ $name }}
                <span>{{ $counts[$key]['total'] }}</span>@if (($counts[$key]['unread'] ?? 0) > 0)<span class="ix-sr"> ({{ $counts[$key]['unread'] }} unread)</span>@endif</a>
        @endforeach
        <a class="ix-notices__archivelink" href="{{ route('notices', array_filter(['category' => $category, 'show' => $archived ? null : 'archived'])) }}">{{ $archived ? 'Back to your list' : 'Archive ('.$archivedTotal.')' }}</a>
    </nav>

    @if ($flash)
        <p class="ix-pts__muted" role="status">{{ $flash }}</p>
    @endif

    @if ($rows->isEmpty())
        <section class="ix-empty">
            <h2>{{ $archived ? 'Nothing is archived' : ($wants ? 'Nothing has happened to your work yet' : 'Notifications are off') }}</h2>
            <p>{{ $wants ? 'When someone answers your question, accepts your answer or verifies your solution, it is listed here. The quickest way to start is to ask something.' : 'Nothing new is being recorded. Tick the box below to turn them on again.' }}</p>
            @if ($wants)
                <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary">Ask a question</a>
                <a href="{{ route('questions') }}" class="ix-btn">Find a question to answer</a>
            @endif
        </section>
    @else
        @foreach ([[$archived ? 'Archived' : 'New', 'notices-new', $new], ['Earlier', 'notices-earlier', $earlier]] as [$heading, $id, $group])
            @continue($group->isEmpty())
            <section class="ix-notices" aria-labelledby="{{ $id }}">
                <h2 class="ix-notices__heading" id="{{ $id }}">{{ $heading }} <span class="ix-notices__n">{{ $group->count() }}</span></h2>
                <ul class="ix-notices__list">
                    @foreach ($group as $row)
                        @php($n = $row['notice'])
                        <li class="ix-notices__item {{ $n->read_at === null ? 'is-unread' : '' }}" data-notice="{{ $n->kind }}">
                            <span class="ix-notices__kind">{{ \App\Services\Knowledge\InAppNotices::label($n->kind) }}</span>
                            <p class="ix-notices__body">
                                @if ($row['url'])
                                    <a href="{{ route('notices.open', ['id' => $n->id]) }}" class="ix-notices__text">{{ $row['text'] }}@if ($n->read_at === null)<span class="ix-sr"> (unread)</span>@endif</a>
                                @else
                                    <span class="ix-notices__text is-gone">{{ $row['text'] }}@if ($n->read_at === null)<span class="ix-sr"> (unread)</span>@endif</span>
                                @endif
                            </p>
                            <time class="ix-notices__time" datetime="{{ $n->created_at->toIso8601String() }}" title="{{ $n->created_at->format('j M Y, H:i') }}">{{ $n->created_at->diffForHumans() }}</time>
                            <div class="ix-notices__acts">
                                @foreach ($row['actions'] ?? [] as $action)
                                    <form method="POST" action="{{ route('notices.answer', ['id' => $n->id, 'answer' => $action['key']]) }}">@csrf
                                        <button type="submit" class="ix-btn ix-btn--sm {{ $action['key'] === 'accept' ? 'ix-btn--primary' : '' }}">{{ $action['label'] }}</button>
                                    </form>
                                @endforeach
                                <form method="POST" action="{{ route($archived ? 'notices.restore' : 'notices.archive', ['id' => $n->id]) }}">@csrf
                                    <button type="submit" class="ix-btn ix-btn--sm ix-btn--ghost" aria-label="{{ $archived ? 'Bring back' : 'Put away' }}: {{ $row['text'] }}">{{ $archived ? 'Bring back' : 'Put away' }}</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @include('partials.pager', ['pager' => $page, 'label' => 'Notifications'])
    @endif

    <form method="POST" action="{{ route('notices.preference') }}" class="ix-notices__pref">
        @csrf
        <input type="hidden" name="notices_in_app" value="0">
        <label class="ix-pts__check" for="notices-on">
            <input type="checkbox" id="notices-on" name="notices_in_app" value="1" aria-describedby="notices-on-hint notices-on-state" @checked($wants)>
            <span>Tell me here when something happens to my work</span>
        </label>
        <p class="ix-hint" id="notices-on-hint">These are replies to your own work, never reminders to come back and never a streak. The one reminder you get is for a seminar you registered for. Notices about a seminar you booked, paid for or host (cancelled, changed, refunded, disputed, removed, or money owed) are always shown, whatever you choose here. Read notices are removed after 90 days.</p>
        <p class="ix-hint" id="notices-on-state">Right now this is <strong>{{ $wants ? 'on' : 'off' }}</strong>.</p>
        <button type="submit" class="ix-btn">Save</button>
    </form>
</x-app-layout>
