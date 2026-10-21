@component('mail::message')
Hello {{ $name }},

{{ $count }} of your Dot Points {{ $verb }} on {{ $date }}.

You can see your balance, and what you can use points for, on your [Dot Points page]({{ $pointsUrl }}).

Dot Points have no cash value, and each award lapses 12 months after it was granted.

You are getting this email because you earn Dot Points on InfoDot. If you would rather not get these emails, you can turn them off under "Email me before my points expire" on your [Dot Points page]({{ $preferenceUrl }}).
@endcomponent
