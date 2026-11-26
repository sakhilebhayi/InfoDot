{{-- R-66 phase 3: what an appointment request asked for, and what the business decided. Shown to both sides. --}}
@if ($enquiry->isAppointmentRequest())
    <section class="ix-biz__card" aria-labelledby="appt-h" data-appointment-request>
        <h2 id="appt-h">Appointment request</h2>
        <p class="ix-trust__line"><b>Asked for:</b> {{ $enquiry->requestedSummary() }}.</p>
        @if ($enquiry->appointment_state === \App\Models\Enquiry::APPOINTMENT_ACCEPTED)
            <p class="ix-trust__line"><span class="ix-chip">Accepted</span> {{ $enquiry->appointmentSummary() }}.</p>
            <p class="ix-hint">Agreed in this conversation; it is not in a booking calendar.</p>
        @elseif ($enquiry->appointment_state === \App\Models\Enquiry::APPOINTMENT_DECLINED)
            <p class="ix-trust__line"><span class="ix-chip">Declined</span> The reason is in the conversation.</p>
        @else
            <p class="ix-trust__line"><span class="ix-chip">Waiting for an answer</span></p>
        @endif
    </section>
@endif
