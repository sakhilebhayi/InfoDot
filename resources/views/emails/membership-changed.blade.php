@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

@if ($added)
You were added to **{{ $company }}** on InfoDot. You can now see its questions, solutions and people, and use the platforms it has switched on.

@component('mail::button', ['url' => $url])
Open InfoDot
@endcomponent
@else
You were removed from **{{ $company }}** on InfoDot. You no longer see its people or its platforms. Your own questions, answers and account stay as they were.

If you think this was a mistake, ask the person who runs {{ $company }} to add you again.
@endif
@endcomponent
