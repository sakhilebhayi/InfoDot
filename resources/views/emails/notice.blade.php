{{-- R-72 (E-03): one notice, in the words the bell uses for this person. The text is already plain (InAppNotices writes it from data the
     reader may see); the link is InfoDot's own. --}}
@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

{{ $text }}

@if ($url)
@component('mail::button', ['url' => $url])
Open it on InfoDot
@endcomponent
@endif

@if ($security)
You get this because it protects your account, so it cannot be switched off. If you do not recognise it, sign in and change your password.
@else
You get this because your notification settings send business news as it happens. [Change your notification settings]({{ $settingsUrl }}).
@endif
@endcomponent
