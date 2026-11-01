@component('mail::message')
Someone applying to verify **{{ $business }}** on InfoDot asked us to confirm that this address belongs to the business.

Your code is **{{ $code }}**. It works for {{ $minutes }} minutes.

Enter it on the business's verification page on InfoDot. If nobody at {{ $business }} asked for this, ignore this email — nothing happens without the code.
@endcomponent
