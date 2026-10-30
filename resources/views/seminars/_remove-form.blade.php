{{-- Moderators: remove a seminar. $seminar, $idPrefix. Before the start everyone who paid is refunded; once it has started the moderator chooses. --}}
<form method="POST" action="{{ route('moderation.seminars.remove', $seminar) }}" class="ix-form" style="margin-top: 8px; max-width: 520px;" x-data @submit="confirm('Remove this seminar? Everyone who registered is told. The host is told why.') || $event.preventDefault()">@csrf
    <label class="ix-label" for="{{ $idPrefix }}-why-{{ $seminar->id }}">Reason (the host sees this)</label>
    <input class="ix-input" id="{{ $idPrefix }}-why-{{ $seminar->id }}" name="reason" type="text" minlength="10" maxlength="500" required>
    @if ($seminar->starts_at->isPast())
        <label class="ix-label" for="{{ $idPrefix }}-refunds-{{ $seminar->id }}">This seminar has already started. Who is refunded?</label>
        <select class="ix-input" id="{{ $idPrefix }}-refunds-{{ $seminar->id }}" name="refunds">
            <option value="all">Everyone who paid, in full</option>
            <option value="complainants">Only people who reported the seminar</option>
            <option value="none">No one</option>
        </select>
    @else
        <p class="ix-hint">It has not started, so everyone who paid is refunded in full.</p>
    @endif
    <button class="ix-btn ix-btn--danger" type="submit">Remove the seminar</button>
</form>
