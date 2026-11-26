@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

{{ $what }} on {{ $at }}.

@if ($kind === 'suspended')
While it is suspended you cannot sign in to InfoDot or the Dot platforms.
@if ($reason !== '')

The reason given: {{ $reason }}
@endif

If you think this is a mistake, reply through the contact page and say which account it is about.

@component('mail::button', ['url' => route('contact')])
Contact InfoDot
@endcomponent
@elseif ($kind === 'restored')
You can sign in again as before.
@if ($reason !== '')

The note given: {{ $reason }}
@endif
@else
If this was you, there is nothing more to do.

@if ($wasPassword || $kind === 'new_sign_in')
If it was not you, someone else may have your password. Reset it now, and every device that was signed in will be signed out:

@component('mail::button', ['url' => $resetUrl])
Reset your password
@endcomponent
@else
If it was not you, sign in, check your account settings and change your password.
@endif
@endif

You get this message because it protects your account, so it cannot be switched off.
@endcomponent
