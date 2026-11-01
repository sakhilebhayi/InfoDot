@component('mail::message')
Hello {{ $name }},

@if ($toCustomer)
**{{ $business }}** replied to your enquiry “{{ $subject }}”.
@elseif ($kind === \App\Models\UserNotice::ENQUIRY_RECEIVED)
**{{ $customer ?? 'Someone' }}** sent **{{ $business }}** an enquiry on InfoDot: “{{ $subject }}”.
@elseif ($kind === \App\Models\UserNotice::ENQUIRY_ASSIGNED)
The enquiry “{{ $subject }}” for **{{ $business }}** has been given to you.
@else
**{{ $customer ?? 'The customer' }}** replied to “{{ $subject }}” for **{{ $business }}**.
@endif

@component('mail::button', ['url' => $url])
Open the conversation
@endcomponent

The message itself stays on InfoDot. You will not get another email about this conversation for {{ config('enquiries.email_quiet_minutes') }} minutes; the bell on InfoDot shows everything as it arrives.
@endcomponent
