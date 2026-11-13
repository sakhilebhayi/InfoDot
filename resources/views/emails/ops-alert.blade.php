InfoDot operations alert, {{ now()->format('Y-m-d H:i') }} ({{ config('app.timezone') }})

@if ($total > 0)
{{ $total }} {{ $total === 1 ? 'error was' : 'errors were' }} logged since {{ $lastAlertAt === null ? 'this alert started watching' : 'the alert at '.$lastAlertAt }}.
@if ($held > 0)
At most one alert is sent an hour: {{ $held }} more since the alert at {{ $lastAlertAt }} {{ $held === 1 ? 'was' : 'were' }} held back and {{ $held === 1 ? 'is' : 'are' }} counted here.
@endif

By exception class:
@foreach ($classes as $class => $count)
  {{ str_pad((string) $count, 5, ' ', STR_PAD_LEFT) }}  {!! $class !!}
@endforeach

First line of {{ count($samples) === 1 ? 'the error' : 'up to five different errors' }} (addresses, tokens and numbers are masked):
@foreach ($samples as $sample)
  {!! $sample !!}
@endforeach
@else
No new errors were logged.
@endif

@if ($checks !== [])
Checks that are not ok (php artisan ops:check):
@foreach ($checks as $kind => $count)
  {{ str_pad($kind, 34) }} {{ $count }}
@endforeach

@endif
Where to look: storage/logs/laravel-{{ now()->format('Y-m-d') }}.log on the host. Stack traces and request data are never emailed.
A check that keeps failing is repeated once a day.
