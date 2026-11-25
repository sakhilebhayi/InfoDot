@php
    $fmt = fn ($t) => $t->copy()->setTimezone($zone)->format('D j M Y, H:i');
    $start = $booking->starts_at->copy()->setTimezone($zone);
    $back = route('business.appointments', $team);
    $fee = $type->fee_cents === null ? 'No fee' : $type->currency.' '.number_format($type->fee_cents / 100, 2, '.', ' ').' (for information; nothing is charged through InfoDot)';
    $holding = in_array($booking->status, \App\Models\Booking::HOLDING, true);
@endphp
<x-app-layout :title="'Appointment '.$booking->reference">
    @include('business.booking._nav', ['team' => $team, 'active' => 'appointments'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $type->name }} with {{ $booking->customer_name }}</h1>
            <p class="ix-lede"><span class="ix-ref">{{ $booking->reference }}</span> @include('business.appointments._status', ['booking' => $booking])</p>
        </div>
        <a href="{{ $back }}" class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>All appointments</a>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    @if ($booking->reschedule_requested_at && $holding)
        <section class="ix-panel" aria-labelledby="ask-h">
            <div class="ix-panel__head"><h2 id="ask-h">The customer asked to move this appointment</h2></div>
            <div class="ix-panel__body">
                <p>{{ $booking->reschedule_note }}</p>
                <p class="ix-hint">Asked {{ $fmt($booking->reschedule_requested_at) }}. Moving it to another time answers the request; so does declining it.</p>
                @if ($can['decline'])
                    <form method="post" action="{{ route('business.appointments.decline_reschedule', [$team, $booking]) }}" class="ix-form">@csrf
                        <div>
                            <label class="ix-label" for="decline-note">Reason for the customer <span class="ix-hint">optional, they will see it</span></label>
                            <input class="ix-input" id="decline-note" name="note" type="text" maxlength="300">
                        </div>
                        <div><button type="submit" class="ix-btn ix-btn--sm">Decline the request to move</button></div>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <section class="ix-panel" aria-labelledby="details-h">
        <div class="ix-panel__head"><h2 id="details-h">The appointment</h2></div>
        <div class="ix-panel__body">
            <dl class="ix-biz__facts">
                <div><dt>When</dt><dd>{{ $start->format('D j M Y, H:i') }} to {{ $booking->ends_at->copy()->setTimezone($zone)->format('H:i') }} <span class="ix-hint">{{ str_replace('_', ' ', $zone->getName()) }} time</span></dd></div>
                <div><dt>Appointment</dt><dd>{{ $type->name }}, {{ $type->duration_minutes }} minutes</dd></div>
                <div><dt>Fee</dt><dd>{{ $fee }}</dd></div>
                <div><dt>Format</dt><dd>{{ \App\Models\AppointmentType::FORMATS[$type->meeting_format] ?? $type->meeting_format }}</dd></div>
                <div><dt>Who takes it</dt><dd>{{ $booking->provider?->public_name ?? 'Not assigned to a person' }}</dd></div>
                @if (filled($booking->status_note))<div><dt>Message to the customer</dt><dd>{{ $booking->status_note }}</dd></div>@endif
            </dl>
        </div>
    </section>

    <section class="ix-panel" aria-labelledby="customer-h">
        <div class="ix-panel__head"><h2 id="customer-h">The customer</h2></div>
        <div class="ix-panel__body">
            <dl class="ix-biz__facts">
                <div><dt>Name</dt><dd>{{ $booking->customer_name }}</dd></div>
                <div><dt>Email</dt><dd><a href="mailto:{{ rawurlencode($booking->customer_email) }}">{{ $booking->customer_email }}</a></dd></div>
                @if ($booking->customer_phone)<div><dt>Phone</dt><dd>{{ $booking->customer_phone }}</dd></div>@endif
                @if ($booking->customer_company)<div><dt>Business</dt><dd>{{ $booking->customer_company }}</dd></div>@endif
                @if ($booking->reason)<div><dt>Reason</dt><dd>{!! nl2br(e($booking->reason)) !!}</dd></div>@endif
            </dl>
        </div>
    </section>

    @if ($can['accept'] || $can['reject'] || $can['cancel'] || $can['finish'] || $can['move'] || $can['assign'])
        <section class="ix-panel" aria-labelledby="do-h">
            <div class="ix-panel__head"><h2 id="do-h">What you can do</h2></div>
            <div class="ix-panel__body">

                @if ($can['accept'])
                    <form method="post" action="{{ route('business.appointments.accept', [$team, $booking]) }}">@csrf<button type="submit" class="ix-btn ix-btn--primary">Accept this request</button></form>
                @endif

                @if ($can['finish'])
                    <p class="ix-hint">This appointment has started. Say how it went.</p>
                    <div class="ix-actions">
                        <form method="post" action="{{ route('business.appointments.complete', [$team, $booking]) }}">@csrf<button type="submit" class="ix-btn ix-btn--primary">Mark as done</button></form>
                        <form method="post" action="{{ route('business.appointments.no_show', [$team, $booking]) }}">@csrf<button type="submit" class="ix-btn">Mark as a no-show</button></form>
                    </div>
                @endif

                @if ($can['reject'])
                    <form method="post" action="{{ route('business.appointments.reject', [$team, $booking]) }}" class="ix-form" data-confirm="Reject this request? The customer is told it could not be taken." data-confirm-label="Reject">@csrf
                        <div>
                            <label class="ix-label" for="reject-note">Reason for the customer <span class="ix-hint">optional, they will see it</span></label>
                            <input class="ix-input" id="reject-note" name="note" type="text" maxlength="300">
                        </div>
                        <div><button type="submit" class="ix-btn ix-btn--danger">Reject this request</button></div>
                    </form>
                @endif

                @if ($can['cancel'])
                    <form method="post" action="{{ route('business.appointments.cancel', [$team, $booking]) }}" class="ix-form" data-confirm="Cancel this appointment? The customer is told, and the time becomes free." data-confirm-label="Cancel the appointment">@csrf
                        <div>
                            <label class="ix-label" for="cancel-note">Reason for the customer <span class="ix-hint">optional, they will see it</span></label>
                            <input class="ix-input" id="cancel-note" name="note" type="text" maxlength="300">
                        </div>
                        <div><button type="submit" class="ix-btn ix-btn--danger">Cancel this appointment</button></div>
                    </form>
                @endif
            </div>
        </section>
    @endif

    @if ($can['move'])
        <section class="ix-panel" aria-labelledby="move-h">
            <div class="ix-panel__head"><h2 id="move-h">Move to another time</h2></div>
            <div class="ix-panel__body">
                <form method="get" action="{{ route('business.appointments.show', [$team, $booking]) }}#move-h" class="ix-form">
                    <div>
                        <label class="ix-label" for="move">Day <span class="ix-hint">see the free times on that day</span></label>
                        <input class="ix-input" id="move" name="move" type="date" required value="{{ $move?->format('Y-m-d') }}">
                    </div>
                    <div><button type="submit" class="ix-btn ix-btn--sm">Show free times</button></div>
                </form>
                @if ($move)
                    @if ($slots->isEmpty())
                        <p class="ix-hint">Nothing is free on {{ $move->format('D j M Y') }}. Try another day.</p>
                    @else
                        <form method="post" action="{{ route('business.appointments.reschedule', [$team, $booking]) }}" class="ix-form">@csrf
                            <fieldset class="ix-choice">
                                <legend class="ix-label">Free times on {{ $move->format('D j M Y') }}</legend>
                                @foreach ($slots as $slot)
                                    <label class="ix-choice__opt"><input type="radio" name="start" value="{{ $slot['stamp'] }}" required><span>{{ $slot['local']->format('H:i') }}</span></label>
                                @endforeach
                            </fieldset>
                            <div><button type="submit" class="ix-btn ix-btn--primary">Move the appointment</button></div>
                        </form>
                    @endif
                @endif
            </div>
        </section>
    @endif

    @if ($can['assign'])
        <section class="ix-panel" aria-labelledby="assign-h">
            <div class="ix-panel__head"><h2 id="assign-h">Hand over</h2></div>
            <div class="ix-panel__body">
                <form method="post" action="{{ route('business.appointments.assign', [$team, $booking]) }}" class="ix-form">@csrf
                    <div>
                        <label class="ix-label" for="provider">Who takes it</label>
                        <select class="ix-input" id="provider" name="provider">
                            <option value="any">Whoever is free</option>
                            @foreach ($providers as $p)
                                <option value="{{ $p->id }}" @selected($booking->booking_provider_id === $p->id)>{{ $p->public_name }}</option>
                            @endforeach
                        </select>
                        <p class="ix-hint">The person must be free at this time.</p>
                    </div>
                    <div><button type="submit" class="ix-btn ix-btn--sm">Hand over</button></div>
                </form>
            </div>
        </section>
    @endif

    <section class="ix-panel" aria-labelledby="notes-h">
        <div class="ix-panel__head"><h2 id="notes-h">Internal notes</h2></div>
        <div class="ix-panel__body">
            <form method="post" action="{{ route('business.appointments.note', [$team, $booking]) }}" class="ix-form">@csrf
                <div>
                    <label class="ix-label" for="internal_notes">Only your team sees this. The customer never does.</label>
                    <textarea class="ix-textarea" id="internal_notes" name="internal_notes" rows="4" maxlength="2000">{{ old('internal_notes', $booking->internal_notes) }}</textarea>
                </div>
                <div><button type="submit" class="ix-btn ix-btn--sm">Save the note</button></div>
            </form>
        </div>
    </section>

    <section class="ix-panel" aria-labelledby="history-h">
        <div class="ix-panel__head"><h2 id="history-h">History</h2></div>
        <div class="ix-panel__body">
            @if ($events->isEmpty())
                <p class="ix-hint">Nothing is recorded yet.</p>
            @else
                <ol class="ix-mod__history">
                    @foreach ($events as $e)
                        <li>{{ $words[$e->type] ?? ucfirst(str_replace('_', ' ', $e->type)) }}, by {{ $e->actor_id !== null && (int) $e->actor_id === (int) $booking->customer_user_id ? 'the customer' : ($e->actor?->name ?? 'someone who has left') }}, {{ $fmt($e->created_at) }}</li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</x-app-layout>
