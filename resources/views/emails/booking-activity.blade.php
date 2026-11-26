@component('mail::message')
Hello {{ $name }},

@switch($kind)
@case(\App\Models\UserNotice::BOOKING_RECEIVED)
A customer asked **{{ $business }}** for **{{ $type }}** on {{ $when }}. It is waiting for an answer.
@break
@case(\App\Models\UserNotice::BOOKING_SUBMITTED)
Your request to **{{ $business }}** for **{{ $type }}** on {{ $when }} has been sent. The time is held for you until they confirm it.
@break
@case(\App\Models\UserNotice::BOOKING_CONFIRMED)
**{{ $business }}** confirmed your **{{ $type }}** on {{ $when }}. A calendar file is attached.
@break
@case(\App\Models\UserNotice::BOOKING_REJECTED)
**{{ $business }}** could not take your request for **{{ $type }}** on {{ $when }}.
@break
@case(\App\Models\UserNotice::BOOKING_CANCELLED)
@if ($toCustomer)
**{{ $business }}** cancelled your **{{ $type }}** on {{ $when }}.
@else
The customer cancelled **{{ $type }}** on {{ $when }}. The time is free again.
@endif
@break
@case(\App\Models\UserNotice::BOOKING_RESCHEDULED)
**{{ $business }}** moved your **{{ $type }}** to {{ $when }}. A calendar file with the new time is attached.
@break
@case(\App\Models\UserNotice::BOOKING_RESCHEDULE_REQUESTED)
The customer asked to move **{{ $type }}** on {{ $when }}. Open the appointment to move it or keep it.
@break
@case(\App\Models\UserNotice::BOOKING_RESCHEDULE_DECLINED)
**{{ $business }}** kept your **{{ $type }}** at {{ $when }}; it could not be moved.
@break
@case(\App\Models\UserNotice::BOOKING_EXPIRED)
**{{ $business }}** did not answer your request for **{{ $type }}** on {{ $when }} in time, so it was let go and the time is free for others. You are welcome to book again.
@break
@case(\App\Models\UserNotice::BOOKING_REMINDER)
@if ($hours === 1)
**{{ $type }}** with **{{ $business }}** starts in about an hour: {{ $when }}.
@else
**{{ $type }}** with **{{ $business }}** is coming up: {{ $when }}.
@endif
@break
@endswitch

@if ($hasMessage)
{{ $business }} left you a message. Open the appointment to read it.
@endif

Reference: {{ $reference }}

@component('mail::button', ['url' => $url])
Open the appointment
@endcomponent

If the button does not open, sign in to InfoDot with this email address and open {{ $toCustomer ? 'My bookings' : 'Appointments' }}: {{ $listUrl }}

The details of the appointment stay on InfoDot, behind your sign-in.

[Choose which emails you get]({{ $settingsUrl }})
@endcomponent
