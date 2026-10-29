<x-app-layout>
    @php($plan = \App\Services\Seminars\P2pCapacity::plan((int) $seminar->p2p_uplink_kbps, (string) $seminar->p2p_quality))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $seminar->title }}</h1>
            <p class="ix-lede">{{ $isHost ? 'You are the host. Your computer sends the video, so keep this page open and your internet steady.' : 'Live from the host in a small room. You may also be passing the stream on to a few other people, so keep this page open.' }}</p>
        </div>
        <a class="ix-btn" href="{{ route('seminars.show', $seminar) }}">Back to the seminar</a>
    </div>

    <div id="p2p-room" class="ix-room"
         data-host="{{ $isHost ? 1 : 0 }}" data-quality="{{ $seminar->p2p_quality }}" data-kbps="{{ (int) (config('seminars.p2p.qualities.'.$seminar->p2p_quality.'.kbps') ?? 0) }}" data-uplink-kbps="{{ (int) $seminar->p2p_uplink_kbps }}"
         data-allow-fake="{{ config('app.debug') ? 1 : 0 }}"
         data-join-url="{{ route('seminars.p2p.join', $seminar) }}" data-poll-url="{{ route('seminars.p2p.poll', $seminar) }}" data-signal-url="{{ route('seminars.p2p.signal', $seminar) }}"
         data-ready-url="{{ route('seminars.p2p.ready', $seminar) }}" data-report-url="{{ route('seminars.p2p.report', $seminar) }}" data-leave-url="{{ route('seminars.p2p.leave', $seminar) }}" data-chat-url="{{ route('seminars.p2p.chat', $seminar) }}">
        <noscript><p class="ix-error">The room needs JavaScript. Turn it on in your browser and reload.</p></noscript>
        <p class="ix-hint" role="status" aria-live="polite" data-status>Connecting…</p>
        <p class="ix-hint" role="status" aria-live="polite" data-note></p>
        <div class="ix-room__grid">
            <div class="ix-room__stage"><video data-video autoplay playsinline @if ($isHost) muted @endif aria-label="Video"></video></div>
            <aside class="ix-room__side">
                <p class="ix-hint"><b data-count>1</b> in the room · up to {{ $seminar->capacity }}</p>
                <ul class="ix-room__chat" data-chat-log aria-live="polite" aria-label="Chat"></ul>
                <form data-chat-form class="ix-room__form">
                    <label class="ix-sr" for="p2p-chat">Message</label>
                    <input class="ix-input" id="p2p-chat" data-chat-input type="text" maxlength="300" placeholder="Say something" autocomplete="off">
                    <button class="ix-btn" type="submit">Send</button>
                </form>
            </aside>
        </div>
        <div class="ix-room__controls">
            @if ($isHost)
                <button class="ix-btn" type="button" data-toggle="mic" aria-pressed="true">Microphone</button>
                @if ($seminar->p2p_quality !== 'audio')
                    <button class="ix-btn" type="button" data-toggle="cam" aria-pressed="true">Camera</button>
                    <button class="ix-btn" type="button" data-toggle="screen" aria-pressed="false">Share screen</button>
                @endif
            @else
                <button class="ix-btn" type="button" data-unmute>Turn sound on</button>
            @endif
            <button class="ix-btn ix-btn--ghost" type="button" data-leave>Leave</button>
        </div>
        @if ($isHost)
            <p class="ix-hint">This room can hold up to <b>{{ $seminar->capacity }}</b> people ({{ $plan['label'] }}). Your computer feeds {{ $plan['direct'] }} directly; the rest receive it through other viewers.</p>
        @endif
    </div>
    {!! \App\Support\OptionalVite::tags('resources/js/seminar-p2p.js') !!}
</x-app-layout>
