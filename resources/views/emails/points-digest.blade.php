@component('mail::message')
Hello {{ $name }},

Here is what you earned on InfoDot in {{ $when }}.

You earned {{ $earned }}:

@foreach ($awards as $award)
- {{ $award['points'] }}, {{ $award['times'] }}: {{ $award['label'] }}
@endforeach

@if ($reversed)
Separately, {{ $reversed }} that were awarded earlier were taken back in this period, because those awards turned out to be invalid or duplicated. They are not subtracted from the {{ $earned }} above, and your balance below already takes them into account.

@endif
Your balance is {{ $balance }}.

@if ($expiringPoints)
Points lapse 12 months after they were granted. {{ $expiringPoints }} of yours lapse within the next 30 days, the first on {{ $expiringFirst }}.

@endif
You can see your balance, and what you can use points for, on your [Dot Points page]({{ $pointsUrl }}). Dot Points have no cash value.

You are getting this email because you chose a {{ $summaryName }} summary of your Dot Points. To stop it, choose "No, do not send me summaries" under "Summary emails" on your [Dot Points page]({{ $preferenceUrl }}). Nothing else changes.
@endcomponent
