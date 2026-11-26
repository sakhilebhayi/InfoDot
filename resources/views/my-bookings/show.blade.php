@php
    $zone = ($booking->team->bookingSetting ?? \App\Models\BookingSetting::forTeam($booking->team))->zone();
    $type = $booking->appointmentType;
    $why = $bookings->whyNotCancel($booking, auth()->user());
    $visible = $booking->team->isVisibleTo(auth()->user());
    $holding = in_array($booking->status, \App\Models\Booking::HOLDING, true);
    $whyMove = $holding ? $bookings->whyNotReschedule($booking, auth()->user()) : null;
    $asked = $holding && $booking->reschedule_requested_at !== null;
@endphp
<x-app-layout :title="'Booking '.$booking->reference">
    @push('meta')<meta name="robots" content="noindex">@endpush

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $type->name }}</h1>
            <p class="ix-lede"><span class="ix-ref">{{ $booking->reference }}</span> with @if ($visible)<a href="{{ route('business.show', $booking->team) }}">{{ $booking->team->name }}</a>@else{{ $booking->team->name }}@endif</p>
        </div>
        <a href="{{ route('my-bookings') }}" class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>All my bookings</a>
    </div>

    @if (session('status') === 'booked')
        @if ($booking->status === 'pending')
            <p class="ix-alert" role="status"><b>Your request is in.</b> {{ $booking->team->name }} has to accept it before the appointment is confirmed. Until then the time is held for you, you can cancel it here, and this page shows the answer. Nothing is charged through InfoDot.</p>
        @else
            <p class="ix-alert" role="status"><b>Your appointment is confirmed.</b> Nothing is charged through InfoDot.</p>
        @endif
    @elseif (session('status') === 'cancelled')
        <p class="ix-alert" role="status">Your appointment has been cancelled and the time is free for others.</p>
    @elseif (session('status') === 'reschedule_requested')
        <p class="ix-alert" role="status">Your request to move this appointment has been sent. {{ $booking->team->name }} will answer here. Until then the appointment stays as it is.</p>
    @endif
    @if ($errors->has('booking'))<div class="ix-error" role="alert"><p>{{ $errors->first('booking') }}</p></div>@endif

    <x-booking-zone :zone="$zone->getName()">
        <section class="ix-panel" aria-label="Your booking">
            <div class="ix-panel__body">
                <p><b>@include('my-bookings._status', ['booking' => $booking])</b></p>
                @if (filled($booking->status_note) && $booking->status !== 'expired')<p>A message from {{ $booking->team->name }}: {{ $booking->status_note }}</p>@endif
                @if ($asked)<p>You asked to move this appointment: {{ $booking->reschedule_note }}. {{ $booking->team->name }} will answer here.</p>@endif
                @if ($booking->status === 'pending')<p class="ix-hint">{{ $booking->team->name }} has not yet accepted this request. You will see the answer on this page.</p>@endif
                <dl class="ix-biz__facts">
                    <div><dt>When</dt><dd><time datetime="{{ $booking->starts_at->utc()->format('Y-m-d\TH:i:s\Z') }}" data-show="full">{{ $booking->starts_at->setTimezone($zone)->format('D j M Y, H:i') }}</time> <span class="ix-hint">{{ $zone->getName() }} time ({{ $booking->starts_at->setTimezone($zone)->format('T') }})</span></dd></div>
                </dl>
                @include('business.book._facts', ['type' => $type, 'mine' => true])
                <dl class="ix-biz__facts">
                    <div><dt>Name</dt><dd>{{ $booking->customer_name }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $booking->customer_email }}</dd></div>
                    @if ($booking->customer_phone)<div><dt>Phone</dt><dd>{{ $booking->customer_phone }}</dd></div>@endif
                    @if ($booking->customerTeam)<div><dt>Booked for</dt><dd><a href="{{ route('business.show', $booking->customerTeam) }}">{{ $booking->customerTeam->name }}</a></dd></div>@elseif ($booking->customer_company)<div><dt>Your business</dt><dd>{{ $booking->customer_company }}</dd></div>@endif
                    @if ($booking->reason)<div><dt>Your note</dt><dd>{!! nl2br(e($booking->reason)) !!}</dd></div>@endif
                </dl>
            </div>
        </section>
    </x-booking-zone>

    @if ($why === null)
        <form method="post" action="{{ route('my-bookings.cancel', $booking->reference) }}" data-confirm="Cancel this appointment? The business is told, and the time becomes free for someone else." data-confirm-label="Cancel the appointment">@csrf
            <button type="submit" class="ix-btn ix-btn--danger">Cancel this appointment</button>
        </form>
    @elseif ($holding && ! ($whyMove !== null && str_contains($whyMove, 'too close')))
        <p class="ix-hint">{{ $why }}</p>
    @endif

    @if ($holding && $whyMove === null)
        <section class="ix-panel" aria-labelledby="move-h">
            <div class="ix-panel__body">
                <h2 id="move-h" class="ix-h2">Ask to move this appointment</h2>
                <p class="ix-hint">{{ $booking->team->name }} decides. Nothing changes until they answer, and the appointment stays booked for now.</p>
                <form method="post" action="{{ route('my-bookings.reschedule', $booking->reference) }}" class="ix-form">@csrf
                    <div>
                        <label class="ix-label" for="note">When would suit you, or why do you need to move it?</label>
                        <textarea class="ix-textarea" id="note" name="note" rows="3" minlength="3" maxlength="300" required>{{ old('note') }}</textarea>
                        @error('note')<p class="ix-hint" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div><button type="submit" class="ix-btn">Send the request</button></div>
                </form>
            </div>
        </section>
    @elseif ($holding && ! $asked && $whyMove !== $why)
        <p class="ix-hint">{{ $whyMove }}</p>
    @endif
</x-app-layout>
