<x-app-layout>
    @php($notice = session('notices_notice'))

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Notifications</h1>
            <p class="ix-lede">What has happened to your own work: an answer to your question, a comment on it, your answer accepted, your solution verified, your work added to the knowledge library. Only you see this page.</p>
        </div>
        @if ($unread > 0)
            <form method="POST" action="{{ route('notices.read-all') }}">
                @csrf
                <button type="submit" class="ix-btn">Mark all as read</button>
            </form>
        @endif
    </div>

    @if ($notice)
        <p class="ix-pts__muted" role="status">{{ $notice }}</p>
    @endif

    @if ($rows->isEmpty())
        <section class="ix-empty">
            <h2>Nothing yet</h2>
            <p>{{ $wants ? 'When someone answers your question, or a moderator verifies your solution, it will be listed here.' : 'Notifications are off, so nothing new is being recorded.' }}</p>
        </section>
    @else
        <ul class="ix-notices" aria-label="Your notifications">
            @foreach ($rows as $row)
                @php($n = $row['notice'])
                <li class="ix-notices__item {{ $n->read_at === null ? 'is-unread' : '' }}" data-notice="{{ $n->kind }}">
                    @if ($row['url'])
                        <a href="{{ route('notices.open', ['id' => $n->id]) }}" class="ix-notices__text">{{ $row['text'] }}</a>
                    @else
                        <span class="ix-notices__text">{{ $row['text'] }}</span>
                    @endif
                    <span class="ix-hint">
                        <time datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->format('j M Y, H:i') }}</time>
                        @if ($n->read_at === null) <strong class="ix-notices__flag">Unread</strong> @endif
                    </span>
                </li>
            @endforeach
        </ul>

        {{ $page->links() }}
    @endif

    <form method="POST" action="{{ route('notices.preference') }}" class="ix-notices__pref">
        @csrf
        <input type="hidden" name="notices_in_app" value="0">
        <label class="ix-pts__check" for="notices-on">
            <input type="checkbox" id="notices-on" name="notices_in_app" value="1" aria-describedby="notices-on-hint notices-on-state" @checked($wants)>
            <span>Tell me here when something happens to my work</span>
        </label>
        <p class="ix-hint" id="notices-on-hint">These are replies to your own work, never reminders to come back and never a streak. Read notices are removed after 90 days.</p>
        <p class="ix-hint" id="notices-on-state">Right now this is <strong>{{ $wants ? 'on' : 'off' }}</strong>.</p>
        <button type="submit" class="ix-btn">Save</button>
    </form>
</x-app-layout>
