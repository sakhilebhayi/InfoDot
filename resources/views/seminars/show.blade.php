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
                @if ($seminar->isP2p())<span><b>Small room: up to {{ $seminar->capacity }} people</b>, set by the host's internet connection</span>@endif
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

    <p class="ix-hint">Hosted by @if ($host->exists)<a href="{{ route('profile.show', ['id' => $host->id]) }}">{{ $host->name }}</a>@else{{ $host->name }}@endif.
        @if ($stats['hosted'] > 0)
            They have hosted {{ $stats['hosted'] }} {{ $stats['hosted'] === 1 ? 'seminar' : 'seminars' }} with {{ $stats['attendees'] }} {{ $stats['attendees'] === 1 ? 'person' : 'people' }} attending{{ $stats['average'] !== null ? ', rated '.$stats['average'].' out of 5 by '.$stats['ratings'].' people' : '' }}.
        @else
            This is their first seminar on InfoDot.
        @endif
    </p>

    @if ($seminar->removed_at && $isHost)
        <p class="ix-error" role="status">A moderator removed this seminar{{ $seminar->removed_reason ? ': '.$seminar->removed_reason : '.' }}
            {{ match ($seminar->removed_refunds) { 'complainants' => 'It had already started, so only people who reported it are refunded.', 'none' => 'It had already started, so tickets were not refunded.', default => 'Anyone who paid has been refunded in full.' } }}</p>
        @error('appeal')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
        @if (! $appeal)
            @if ($seminar->removed_at->gte(now()->subDays((int) config('seminars.removal_appeal_days', 30))))
                <form method="POST" action="{{ route('seminars.appeal', $seminar) }}" class="ix-form" style="max-width: 520px; margin-bottom: 16px;">@csrf
                    <label class="ix-label" for="appeal-msg">Think this was a mistake? Appeal once. A different moderator reads it.</label>
                    <textarea class="ix-input" id="appeal-msg" name="message" rows="4" minlength="10" maxlength="1000" required></textarea>
                    <p class="ix-hint">You have {{ (int) config('seminars.removal_appeal_days', 30) }} days from the removal. If the seminar has not started and the moderator agrees, it is put back on. Refunds already made stay made.</p>
                    <button class="ix-btn" type="submit">Send my appeal</button>
                </form>
            @else
                <p class="ix-hint">The time to appeal this removal has passed.</p>
            @endif
        @elseif ($appeal->status === 'open')
            <p class="ix-hint">Your appeal was sent. A different moderator will read it and the answer will appear here.</p>
        @else
            <p class="ix-hint">Appeal {{ $appeal->status === 'reinstated' ? 'upheld' : 'denied' }}: {{ $appeal->resolution_note }}</p>
        @endif
    @elseif ($isHost && $appeal && $appeal->status === 'reinstated')
        <p class="ix-hint">A moderator put this seminar back on after your appeal: {{ $appeal->resolution_note }}</p>
    @endif

    @if ($canReportPerson)
        <details class="ix-hint" id="report-person" style="margin-bottom: 12px;">
            <summary>Report someone in this seminar</summary>
            <form method="POST" action="{{ route('seminars.report', $seminar) }}" class="ix-form" style="margin-top: 8px; max-width: 480px;">@csrf
                <label class="ix-label" for="rp-name">Their name as it shows in the room or chat</label>
                <input class="ix-input" id="rp-name" name="about_name" type="text" maxlength="60" required>
                <label class="ix-label" for="rp-reason">What happened</label>
                <select class="ix-input" id="rp-reason" name="reason" required>
                    @foreach (\App\Models\SeminarReport::REASONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <label class="ix-label" for="rp-detail">Tell the moderators more (optional)</label>
                <textarea class="ix-input" id="rp-detail" name="detail" rows="3" maxlength="1000"></textarea>
                <p class="ix-hint">A moderator reads it. The person is not told who reported them. As host you can also remove someone from the room yourself.</p>
                <button class="ix-btn ix-btn--sm" type="submit">Send report</button>
            </form>
        </details>
    @endif

    @if (! $isHost && $seminar->status === 'published')
        <details class="ix-hint" style="margin-bottom: 12px;">
            <summary>Report this seminar</summary>
            <form method="POST" action="{{ route('seminars.report', $seminar) }}" class="ix-form" style="margin-top: 8px; max-width: 480px;">@csrf
                <label class="ix-label" for="report-reason">What is wrong with it</label>
                <select class="ix-input" id="report-reason" name="reason" required>
                    @foreach (\App\Models\SeminarReport::REASONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <label class="ix-label" for="report-detail">Tell the moderators more (optional)</label>
                <textarea class="ix-input" id="report-detail" name="detail" rows="3" maxlength="1000"></textarea>
                <p class="ix-hint">A moderator reads it. The host is not told who reported.</p>
                <button class="ix-btn ix-btn--sm" type="submit">Send report</button>
            </form>
        </details>
    @endif

    @if (! $isHost && $seminar->status === 'published')
        <section class="ix-notices__pref" aria-label="Your registration">
            @if ($reg && $reg->status === 'registered')
                <p><span class="ix-chip ix-chip--done">You are registered</span></p>
                @if ($seminar->joinIsOpen())
                    <form method="POST" action="{{ route('seminars.join', $seminar) }}">@csrf<button class="ix-btn ix-btn--primary" type="submit">{{ $seminar->isRoom() || $seminar->isP2p() ? 'Enter the room' : 'Join the seminar' }}</button></form>
                    <p class="ix-hint">{{ $seminar->isRoom() ? 'This opens the InfoDot room in your browser. You are noted as attending when you connect.' : ($seminar->isP2p() ? 'This opens a small room in your browser. Keep the page open: your computer may pass the video on to a few other people.' : 'This opens the host’s own meeting room. Opening it also notes that you attended.') }}</p>
                    @if (! $seminar->isRoom() && ! $seminar->isP2p())
                        <form method="POST" action="{{ route('seminars.report-link', $seminar) }}" style="margin-top: 8px;">@csrf
                            <button class="ix-btn ix-btn--sm ix-btn--ghost" type="submit">The link did not open</button>
                        </form>
                    @endif
                @elseif ($seminar->hasEnded())
                    <p class="ix-hint">This seminar has finished.</p>
                @else
                    <p class="ix-hint">The join button appears {{ config('seminars.join_opens_minutes_before', 15) }} minutes before the start. You will get a reminder here a day and an hour before.</p>
                @endif
                @if (! $seminar->hasEnded())
                    @php($refundOpen = app(\App\Services\Seminars\SeminarService::class)->refundWindowOpen($seminar))
                    <form method="POST" action="{{ route('seminars.unregister', $seminar) }}">@csrf
                        <button class="ix-btn" type="submit">{{ ! $seminar->isPaid() ? 'Cancel my registration' : ($refundOpen ? 'Cancel and get a full refund' : 'Cancel (no refund: starts in under '.config('seminars.paid.refund_hours_before', 24).' hours)') }}</button>
                    </form>
                @endif
                @if ($seminar->isPaid() && $reg->paid_at)
                    <p class="ix-hint"><a href="{{ route('seminars.receipt', $seminar) }}" style="text-decoration: underline;">View your receipt</a>@if ($reg->refunded_cents > 0) · refunded {{ \App\Models\Seminar::money($reg->refunded_cents) }}@endif</p>
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
                        <p class="ix-hint">You pay on Paystack. If you cancel at least {{ config('seminars.paid.refund_hours_before', 24) }} hours before the start you get a full refund; later than that there is no refund. If the host cancels the seminar you are always refunded in full.</p>
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
            @if ($linkReports > 0)
                <p class="ix-error" role="alert">{{ $linkReports }} {{ $linkReports === 1 ? 'person says' : 'people say' }} your meeting link did not open. <a href="{{ route('seminars.edit', $seminar) }}" style="text-decoration: underline;">Check it and fix it</a> before the start.</p>
            @endif
            @if ($seminar->status === 'published')
                <div class="ix-field">
                    <label class="ix-label" for="share-link">Share this seminar</label>
                    <input class="ix-input" id="share-link" type="text" readonly value="{{ $seminar->shareUrl() }}" onfocus="this.select()">
                    <p class="ix-hint">This InfoDot link always opens the seminar page (people sign in first if they need to). Share it instead of the meeting link, which stays private to the people who registered.</p>
                </div>
            @endif
            <h2 class="ix-label" id="people-h">People ({{ $people->count() }})</h2>
            @if ($hostPoints = app(\App\Services\Seminars\SeminarHostPoints::class)->sentence($seminar))<p class="ix-hint" id="host-points">{{ $hostPoints }}</p>@endif
            @if ($seminar->status === 'published')
                <p class="ix-hint">Your meeting link is private to you and the people who registered.</p>
                <form method="POST" action="{{ route('seminars.join', $seminar) }}">@csrf<button class="ix-btn" type="submit">{{ $seminar->isRoom() || $seminar->isP2p() ? 'Enter the room' : 'Open my meeting room' }}</button></form>
            @endif
            @if ($revenue)
                <p class="ix-hint">Tickets sold: <b>{{ $revenue['tickets'] }}</b>. Paid by attendees {{ \App\Models\Seminar::money($revenue['gross_cents']) }}; InfoDot keeps {{ \App\Models\Seminar::money($revenue['platform_cents']) }}; yours {{ \App\Models\Seminar::money($revenue['host_cents']) }} before Paystack's processing fee. Paystack pays you out on its own schedule.</p>
            @endif
            @if ($people->isEmpty())
                <p class="ix-hint">Nobody has registered yet.</p>
            @else
                <table class="ix-table">
                    <thead><tr><th scope="col">Person</th><th scope="col">Status</th><th scope="col">Attended</th>@if ($seminar->isPaid())<th scope="col">Ticket</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($people as $p)
                            <tr>
                                <td>{{ $p->user->name }}</td>
                                <td>{{ $p->status === 'waitlist' ? 'Waiting list' : 'Registered' }}</td>
                                <td>
                                    @if ($p->status === 'registered' && $seminar->isP2p() && config('seminars.p2p.enabled'))
                                        <form method="POST" action="{{ route('seminars.p2p.remove', [$seminar, $p->user_id]) }}" style="display: inline;" onsubmit="return confirm('Remove this person from the room?');">@csrf<button class="ix-btn ix-btn--sm" type="submit">Remove from room</button></form>
                                    @endif
                                    @if ($p->status === 'registered' && $seminar->isRoom() && config('seminars.video.enabled'))
                                        <form method="POST" action="{{ route('seminars.room.remove', [$seminar, $p->user_id]) }}" style="display: inline;" onsubmit="return confirm('Remove this person from the room?');">@csrf<button class="ix-btn ix-btn--sm" type="submit">Remove from room</button></form>
                                    @endif
                                    @if ($p->status === 'registered')
                                        <form method="POST" action="{{ route('seminars.attendance', [$seminar, $p->user_id]) }}">@csrf
                                            <input type="hidden" name="attended" value="{{ $p->attended_at ? 0 : 1 }}">
                                            <button class="ix-btn ix-btn--sm" type="submit">{{ $p->attended_at ? 'Yes (undo)' : 'Mark attended' }}</button>
                                        </form>
                                    @endif
                                </td>
                                @if ($seminar->isPaid())
                                    <td>
                                        {{ match ($p->payment_status) { 'paid' => 'Paid', 'partially_refunded' => 'Part refunded', 'refunded' => 'Refunded', 'chargeback' => 'Charged back', default => 'Not paid' } }}
                                        @if ($p->dispute_status === 'open')<span class="ix-chip">Disputed</span>@endif
                                        @if (in_array($p->payment_status, ['paid', 'partially_refunded'], true) && $p->dispute_status !== 'open' && $p->refundableCents() > 0)
                                            <form method="POST" action="{{ route('seminars.refund', [$seminar, $p->user_id]) }}" style="display: flex; gap: 6px; margin-top: 6px;" onsubmit="return confirm('Refund this amount to the attendee? This cannot be undone.');">@csrf
                                                <label class="ix-sr" for="rf-{{ $p->id }}">Refund amount in rand</label>
                                                <input class="ix-input" id="rf-{{ $p->id }}" name="amount" type="text" inputmode="decimal" placeholder="{{ number_format($p->refundableCents() / 100, 2, '.', '') }}" style="width: 110px;">
                                                <button class="ix-btn ix-btn--sm" type="submit">Refund</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
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

    @if (auth()->user()->is_moderator && ! $isHost && $seminar->status !== 'cancelled')
        <div style="margin-top: 24px;"><span class="ix-label">Moderator: remove this seminar</span>
            @include('seminars._remove-form', ['seminar' => $seminar, 'idPrefix' => 'show'])
        </div>
    @endif
</x-app-layout>
