<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $seminar->title }}</h1>
            <p class="ix-lede">{{ $isHost ? 'You are the host. Turn on your camera, microphone or screen when you are ready.' : 'Live from the host. You can watch and chat; only the host is on camera.' }}</p>
        </div>
        <a class="ix-btn" href="{{ route('seminars.show', $seminar) }}">Back to the seminar</a>
    </div>

    <div id="seminar-room" class="ix-room" data-token-url="{{ route('seminars.room.token', $seminar) }}" data-host="{{ $isHost ? 1 : 0 }}">
        <noscript><p class="ix-error">The room needs JavaScript. Turn it on in your browser and reload.</p></noscript>
        <p class="ix-hint" role="status" aria-live="polite" data-status>Connecting…</p>
        <div class="ix-room__grid">
            <div class="ix-room__stage" data-stage aria-label="Video"></div>
            <aside class="ix-room__side">
                <p class="ix-hint"><b data-count>1</b> in the room</p>
                <ul class="ix-room__chat" data-chat-log aria-live="polite" aria-label="Chat"></ul>
                <form data-chat-form class="ix-room__form">
                    <label class="ix-sr" for="room-chat">Message</label>
                    <input class="ix-input" id="room-chat" data-chat-input type="text" maxlength="500" placeholder="Say something" autocomplete="off">
                    <button class="ix-btn" type="submit" data-needs-room disabled>Send</button>
                </form>
            </aside>
        </div>
        <div class="ix-room__controls">
            @if ($isHost)
                <button class="ix-btn" type="button" data-toggle="camera" aria-pressed="false" data-needs-room disabled>Camera</button>
                <button class="ix-btn" type="button" data-toggle="microphone" aria-pressed="false" data-needs-room disabled>Microphone</button>
                <button class="ix-btn" type="button" data-toggle="screen" aria-pressed="false" data-needs-room disabled>Share screen</button>
            @endif
            <button class="ix-btn ix-btn--ghost" type="button" data-leave>Leave</button>
        </div>
    </div>
    {!! \App\Support\OptionalVite::tags('resources/js/seminar-room.js') !!}
</x-app-layout>
