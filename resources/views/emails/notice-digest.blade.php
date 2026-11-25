@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

Since your last summary, {{ $count === 1 ? 'one thing is' : $count.' things are' }} waiting for you on InfoDot.

@foreach ($groups as $group)
**{{ $group['name'] }}** ({{ $group['total'] }})

@foreach ($group['items'] as $item)
- {{ $item }}
@endforeach
@if ($group['more'] > 0)
- and {{ $group['more'] }} more
@endif

@endforeach
@component('mail::button', ['url' => $url])
Open your notifications
@endcomponent

You get one summary a day, and only when there is something new. Choose what reaches you, and how, in your [notification settings]({{ $settingsUrl }}), or [stop these summaries]({{ $unsubscribeUrl }}).
@endcomponent
