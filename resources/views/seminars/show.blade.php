<x-app-layout>
    @php($flash = session('seminar_notice'))
    @php($tz = config('app.timezone'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $seminar->title }}</h1>
            <p class="ix-lede">{{ $seminar->summary }}</p>
            <div class="ix-meta">
                <span><b>{{ $seminar->starts_at->timezone($tz)->format('D j M Y, H:i') }}</b> to {{ $seminar->endsAt()->timezone($tz)->format('H:i') }}</span>
                <span>{{ $seminar->priceLabel() }}</span>
                @if ($seminar->topic)<span>{{ $seminar->topic->name }}</span>@endif
                <span>{{ $registeredCount }} registered{{ $seminar->capacity ? ' of '.$seminar->capacity.' places' : '' }}</span>
                @if ($seminar->status !== 'published')<span class="ix-chip">{{ $seminar->status === 'draft' ? 'Draft: only you can see this' : 'Cancelled' }}</span>@endif
            </div>
        </div>
        @if ($isHost && $seminar->status !== 'cancelled')
            <a class="ix-btn" href="{{ route('seminars.edit', $seminar) }}">Edit</a>
        @endif
    </div>

    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif
    @error('seminar')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
    @error('rating')<p class="ix-error" role="alert">{{ $message }}</p>@enderror

    @if ($seminar->description)<p style="white-space: pre-line; max-width: 70ch;">{{ $seminar->description }}</p>@endif

    <p class="ix-hint">Hosted by <a href="{{ route('profile.show', ['id' => $host->id]) }}">{{ $host->name }}</a>.
        @if ($stats['hosted'] > 0)
            They have hosted {{ $stats['hosted'] }} {{ $stats['hosted'] === 1 ? 'seminar' : 'seminars' }} with {{ $stats['attendees'] }} {{ $stats['attendees'] === 1 ? 'person' : 'people' }} attending{{ $stats['average'] !== null ? ', rated '.$stats['average'].' out of 5 by '.$stats['ratings'].' people' : '' }}.
        @else
            This is their first seminar on InfoDot.
        @endif
    </p>

    @if (! $isHost && $seminar->status === 'published')
        <section class="ix-notices__pref" aria-label="Your registration">
            @if ($reg && $reg->status === 'registered')
                <p><span class="ix-chip ix-chip--done">You are registered</span></p>
                @if ($seminar->joinIsOpen())
                    <a class="ix-btn ix-btn--primary" href="{{ route('seminars.join', $seminar) }}" rel="noopener">Join the seminar</a>
                    <p class="ix-hint">This opens the host’s own meeting room. Opening it also notes that you attended.</p>
                @elseif ($seminar->hasEnded())
                    <p class="ix-hint">This seminar has finished.</p>
                @else
                    <p class="ix-hint">The join button appears {{ config('seminars.join_opens_minutes_before', 15) }} minutes before the start. You will get a reminder here a day and an hour before.</p>
                @endif
                @if (! $seminar->hasEnded())
                    <form method="POST" action="{{ route('seminars.unregister', $seminar) }}">@csrf<button class="ix-btn" type="submit">Cancel my registration</button></form>
                @endif
            @elseif ($reg && $reg->status === 'waitlist')
                <p><span class="ix-chip">Waiting list</span> You will be registered automatically if a place opens.</p>
                <form method="POST" action="{{ route('seminars.unregister', $seminar) }}">@csrf<button class="ix-btn" type="submit">Leave the waiting list</button></form>
            @elseif ($reg && $reg->status === 'pending_payment')
                <p><span class="ix-chip">Waiting for payment</span> Your place is held until {{ $reg->hold_expires_at?->timezone($tz)->format('H:i') }}. It becomes a ticket only when the payment is confirmed.</p>
                <form method="POST" action="{{ route('seminars.register', $seminar) }}">@csrf<button class="ix-btn ix-btn--primary" type="submit">Continue to payment</button></form>
            @elseif (! $seminar->hasEnded())
                <form method="POST" action="{{ route('seminars.register', $seminar) }}">@csrf
                    @if ($seminar->isPaid())
                        <button class="ix-btn ix-btn--primary" type="submit" @disabled($seminar->capacity && $registeredCount >= $seminar->capacity)>{{ $seminar->capacity && $registeredCount >= $seminar->capacity ? 'Sold out' : 'Buy a ticket ('.$seminar->priceLabel().')' }}</button>
                        <p class="ix-hint">You pay on Paystack. Tickets are refunded in full if the host cancels the seminar; if you cancel yourself the ticket is not refunded.</p>
                    @else
                        <button class="ix-btn ix-btn--primary" type="submit">{{ $seminar->capacity && $registeredCount >= $seminar->capacity ? 'Join the waiting list' : 'Register for free' }}</button>
                    @endif
                </form>
            @endif

            @if ($reg && $reg->attended_at && $seminar->hasEnded())
                <form method="POST" action="{{ route('seminars.feedback', $seminar) }}" class="ix-form" style="margin-top: 16px;">
                    @csrf
                    <div class="ix-field">
                        <label class="ix-label" for="rating">How was it?</label>
                        <select class="ix-input ix-select" id="rating" name="rating" required>
                            @foreach ([5 => 'Excellent', 4 => 'Good', 3 => 'Fine', 2 => 'Poor', 1 => 'Not useful'] as $v => $l)
                                <option value="{{ $v }}" @selected(($myFeedback->rating ?? null) === $v)>{{ $v }} — {{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="comment">A note for the host (optional)</label>
                        <textarea class="ix-textarea" id="comment" name="comment" rows="3" maxlength="1000">{{ $myFeedback->comment ?? '' }}</textarea>
                        <p class="ix-hint">Only the host reads your note. Your rating counts toward their average once at least {{ config('seminars.min_ratings_shown', 3) }} people have rated.</p>
                    </div>
                    <button class="ix-btn" type="submit">{{ $myFeedback ? 'Update my rating' : 'Send rating' }}</button>
                </form>
            @endif
        </section>
    @endif

    @if ($isHost)
        <section aria-labelledby="people-h" style="margin-top: 24px;">
            <h2 class="ix-label" id="people-h">People ({{ $people->count() }})</h2>
            @if ($seminar->status === 'published')
                <p class="ix-hint">Your meeting link is private to you and the people who registered. You can open it yourself here: <a href="{{ route('seminars.join', $seminar) }}" rel="noopener">open my meeting room</a>.</p>
            @endif
            @if ($revenue)
                <p class="ix-hint">Tickets sold: <b>{{ $revenue['tickets'] }}</b>. Paid by attendees {{ \App\Models\Seminar::money($revenue['gross_cents']) }}; InfoDot keeps {{ \App\Models\Seminar::money($revenue['platform_cents']) }}; yours {{ \App\Models\Seminar::money($revenue['host_cents']) }} before Paystack's processing fee. Paystack pays you out on its own schedule.</p>
            @endif
            @if ($people->isEmpty())
                <p class="ix-hint">Nobody has registered yet.</p>
            @else
                <table class="ix-table">
                    <thead><tr><th scope="col">Person</th><th scope="col">Status</th><th scope="col">Attended</th></tr></thead>
                    <tbody>
                        @foreach ($people as $p)
                            <tr>
                                <td>{{ $p->user->name }}</td>
                                <td>{{ $p->status === 'waitlist' ? 'Waiting list' : 'Registered' }}</td>
                                <td>
                                    @if ($p->status === 'registered')
                                        <form method="POST" action="{{ route('seminars.attendance', [$seminar, $p->user_id]) }}">@csrf
                                            <input type="hidden" name="attended" value="{{ $p->attended_at ? 0 : 1 }}">
                                            <button class="ix-btn ix-btn--sm" type="submit">{{ $p->attended_at ? 'Yes (undo)' : 'Mark attended' }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($feedback->isNotEmpty())
                <h2 class="ix-label" style="margin-top: 20px;">Feedback (only you see the notes)</h2>
                <ul class="ix-list">
                    @foreach ($feedback as $f)
                        <li style="padding: 10px 14px;"><strong>{{ $f->rating }}/5</strong>@if($f->comment) — {{ $f->comment }}@endif</li>
                    @endforeach
                </ul>
            @endif

            @if ($seminar->status !== 'cancelled' && ! $seminar->hasEnded())
                <form method="POST" action="{{ route('seminars.cancel', $seminar) }}" style="margin-top: 20px;" onsubmit="return confirm('Cancel this seminar? Everyone who registered will be told.');">@csrf
                    <button class="ix-btn ix-btn--danger" type="submit">Cancel this seminar</button>
                </form>
            @endif
        </section>
    @endif
</x-app-layout>
