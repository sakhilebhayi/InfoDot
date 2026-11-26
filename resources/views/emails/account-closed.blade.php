@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

Your InfoDot account was deleted on {{ $at }}. Your profile, your sign-in and the companies you owned alone are gone, and you were signed out of InfoDot and every Dot platform.

If you did not ask for this, contact us straight away and say which address the account used.

@component('mail::button', ['url' => $contactUrl])
Contact InfoDot
@endcomponent

You get this message because it confirms a change to your account. It is the last one we send to this address.
@endcomponent
