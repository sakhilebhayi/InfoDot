<x-app-layout>
    @php($flash = session('notices_notice'))
    @php($new = $rows->filter(fn ($r) => $r['notice']->read_at === null))
    @php($earlier = $rows->filter(fn ($r) => $r['notice']->read_at !== null))

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Notifications</h1>
            <p class="ix-lede">What has happened to your own work: an answer to your question, a comment on it, your answer accepted, your solution verified, your work added to the knowledge library. Only you see this page.</p>
        </div>
        @if ($unread > 0)
            <form method="POST" action="{{ route('notices.read-all') }}">
                @csrf
                <button type="submit" class="ix-btn">
                    <span class="material-symbols-rounded" aria-hidden="true">done_all</span>Mark all as read
                </button>
            </form>
        @endif
    </div>

    @if ($flash)
        <p class="ix-pts__muted" role="status">{{ $flash }}</p>
    @endif

    @if ($rows->isEmpty())
        <section class="ix-empty">
            <h2>Nothing yet</h2>
            <p>{{ $wants ? 'When someone answers your question, or a moderator verifies your solution, it will be listed here.' : 'Notifications are off, so nothing new is being recorded.' }}</p>
        </section>
    @else
        @foreach ([['New', 'notices-new', $new], ['Earlier', 'notices-earlier', $earlier]] as [$heading, $id, $group])
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
        <p class="ix-hint" id="notices-on-hint">These are replies to your own work, never reminders to come back and never a streak. Read notices are removed after 90 days.</p>
        <p class="ix-hint" id="notices-on-state">Right now this is <strong>{{ $wants ? 'on' : 'off' }}</strong>.</p>
        <button type="submit" class="ix-btn">Save</button>
    </form>
</x-app-layout>
