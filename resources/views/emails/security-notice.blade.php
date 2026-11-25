@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

{{ $what }} on {{ $at }}.

If this was you, there is nothing more to do.

@if ($wasPassword)
If it was not you, someone else may have your password. Reset it now, and every device that was signed in will be signed out:

@component('mail::button', ['url' => $resetUrl])
Reset your password
@endcomponent
@else
If it was not you, sign in, check your account settings and change your password.
@endif

You get this message because it protects your account, so it cannot be switched off.
@endcomponent
