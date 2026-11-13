<x-app-layout>
    @php($labels = [
        'validation_failures' => 'Form sends people back',
        'empty_searches' => 'Searches find nothing',
        'slow_pages' => 'Slow page',
        'not_found' => 'Address does not exist',
        'server_errors' => 'Server error',
    ])

    @include('livewire.moderation._tabs', ['current' => 'friction'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Where people struggle</h1>
            <p class="ix-lede">Counts of failed forms, empty searches, slow and broken pages, by page. Pages are named by their route, never by the address opened, and no person, session or search word is stored. These are advisory: they point at a page to look at and decide nothing.</p>
        </div>
    </div>

    @unless ($enabled)
        {{-- Said in plain words: a moderator cannot switch this on from here, so the page says who can and what it would count. --}}
        <section class="ix-empty">
            <h2>Counting is off</h2>
            <p>Nothing new is being counted on this copy of InfoDot, so the list below can only show what was counted before. Whoever runs the site switches counting on in its settings. When it is on, it counts failed forms, empty searches, slow pages and errors by page, and stores nothing about the person.</p>
        </section>
    @endunless

    {{-- The same period control as a business's insights page: pills on a sunken strip, the chosen one raised. --}}
    <div class="ix-filters" role="group" aria-label="Period">
        @foreach ([7, 14, 30, 90] as $d)
            <a href="{{ route('moderation.friction', ['days' => $d]) }}" class="ix-pill" @if ($days === $d) aria-current="true" @endif>{{ $d }} days</a>
        @endforeach
    </div>

    <p class="ix-hint">{{ $report['from'] }} to {{ $report['to'] }}: {{ number_format($report['totals']['events']) }} counted events on {{ $report['totals']['routes'] }} pages.</p>

    @if (empty($report['findings']))
        <section class="ix-empty">
            <h2>Nothing stands out</h2>
            <p>A rule needs at least {{ config('ux.rules.min_events') }} observations on a page before it says anything, so a quiet page stays quiet.</p>
        </section>
    @else
        <div class="ix-tablewrap">
            <table class="ix-table">
                <caption class="ix-sr">Pages where people struggle, most affected first</caption>
                <thead>
                    <tr>
                        <th scope="col">Page</th>
                        <th scope="col">What</th>
                        <th scope="col" class="num">Now</th>
                        <th scope="col" class="num">Before</th>
                        <th scope="col">Trend</th>
                        <th scope="col">Trust</th>
                        <th scope="col">A first look</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['findings'] as $f)
                        <tr data-rule="{{ $f['rule'] }}">
                            <th scope="row"><code>{{ $f['route'] }}</code></th>
                            <td>{{ $labels[$f['rule']] ?? $f['rule'] }}</td>
                            <td class="num">{{ number_format($f['rate'] * 100, 1) }}% <span class="ix-hint">({{ $f['events'] }} of {{ $f['of'] }})</span></td>
                            <td class="num">{{ $f['previous_rate'] === null ? 'no data' : number_format($f['previous_rate'] * 100, 1).'%' }}</td>
                            <td>{{ $f['change'] ?? 'not enough to compare' }}</td>
                            <td>{{ $f['confidence'] }}</td>
                            <td>{{ $f['suggestion'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
