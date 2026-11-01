<x-app-layout :title="$team->name.' insights'">
    @php
        $t = $r['totals'];
        $p = $r['previous'];
        $change = function (string $m) use ($t, $p, $r) {
            if ($p[$m] === 0) { return null; }
            $d = (int) round(($t[$m] - $p[$m]) / $p[$m] * 100);

            return ($d > 0 ? '+' : '').$d.'% on the '.$r['days'].' days before';
        };
        $peak = max(1, collect($r['series'])->max('view'), collect($r['series'])->max('search'));
        $contacts = collect(\App\Services\Insights\BusinessInsights::CONTACT)->map(fn ($label, $k) => ['label' => $label, 'n' => $t[$k]]);
        $contactTotal = $contacts->sum('n');
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">What InfoDot is doing for {{ $team->name }}</h1>
            <p class="ix-lede">Counted from what actually happened{{ $r['since'] ? ' since '.$r['since']->format('j M Y') : '' }}. A view is one visitor in a day; your own team, reviewers and previews are not counted, and nothing about who visited is kept.</p>
        </div>
        <div class="ix-filters" role="group" aria-label="Period">
            <a class="ix-pill" href="{{ route('business.insights', [$team, 'days' => 30]) }}" @if($r['days'] === 30) aria-current="true" @endif>30 days</a>
            <a class="ix-pill" href="{{ route('business.insights', [$team, 'days' => 90]) }}" @if($r['days'] === 90) aria-current="true" @endif>90 days</a>
        </div>
    </div>

    <dl class="ix-inboxstats" aria-label="The last {{ $r['days'] }} days">
        <div><dt>Page views</dt><dd>{{ $t['view'] }}</dd>@if ($c = $change('view'))<p class="ix-hint">{{ $c }}</p>@endif</div>
        <div><dt>Appeared in searches</dt><dd>{{ $t['search'] }}</dd>@if ($c = $change('search'))<p class="ix-hint">{{ $c }}</p>@endif</div>
        <div><dt>Contact actions</dt><dd>{{ $contactTotal }}</dd></div>
        <div><dt>Enquiries received</dt><dd>{{ $enquiriesInRange }}</dd>@if ($enquiries['median_first_response_hours'] !== null)<p class="ix-hint">Typical first reply {{ $enquiries['median_first_response_hours'] < 1 ? max(1, (int) round($enquiries['median_first_response_hours'] * 60)).' min' : $enquiries['median_first_response_hours'].' h' }}</p>@endif</div>
    </dl>

    @if ($r['since'] === null)
        <div class="ix-empty ix-vgap">
            <h2>Nothing counted yet</h2>
            <p>Counting starts when people open {{ $team->name }}’s page, find it in a search or use its contact buttons. @if ($team->profile?->visibility !== 'public')Right now only signed-in people can find it; <a href="{{ route('business.edit', $team) }}#directory">listing it for anyone</a> lets visitors find it too.@endif</p>
        </div>
    @else
        <section class="ix-panel ix-vgap" aria-labelledby="trend-h">
            <div class="ix-panel__head"><h2 id="trend-h">Day by day</h2><span class="ix-hint">Bars: page views. Line marks: search appearances.</span></div>
            <div class="ix-panel__body">
                <div class="ix-trend" role="img" aria-label="Page views and search appearances per day for the last {{ $r['days'] }} days; the table below has the numbers">
                    @foreach ($r['series'] as $d)
                        <div class="ix-trend__day" tabindex="0" title="{{ \Illuminate\Support\Carbon::parse($d['day'])->format('j M') }}: {{ $d['view'] }} {{ \Illuminate\Support\Str::plural('view', $d['view']) }}, {{ $d['search'] }} in searches">
                            @if ($d['view'] > 0)<span class="ix-trend__bar" style="height: {{ max(2, round($d['view'] / $peak * 100)) }}%"></span>@endif
                            @if ($d['search'] > 0)<span class="ix-trend__tick" style="bottom: {{ round($d['search'] / $peak * 100) }}%"></span>@endif
                            <span class="ix-trend__tip">{{ \Illuminate\Support\Carbon::parse($d['day'])->format('j M') }}<br>{{ $d['view'] }} {{ \Illuminate\Support\Str::plural('view', $d['view']) }}<br>{{ $d['search'] }} in searches</span>
                        </div>
                    @endforeach
                </div>
                <p class="ix-trend__axis"><span>{{ \Illuminate\Support\Carbon::parse($r['series'][0]['day'])->format('j M') }}</span><span>Peak {{ $peak }}</span><span>Today</span></p>
                <details class="ix-vgap"><summary>The numbers as a table</summary>
                    <div class="ix-tablewrap"><table class="ix-table"><thead><tr><th>Day</th><th class="num">Views</th><th class="num">In searches</th></tr></thead><tbody>
                        @foreach (array_reverse($r['series']) as $d)<tr><td>{{ \Illuminate\Support\Carbon::parse($d['day'])->format('D j M') }}</td><td class="num">{{ $d['view'] }}</td><td class="num">{{ $d['search'] }}</td></tr>@endforeach
                    </tbody></table></div>
                </details>
            </div>
        </section>

        <div class="ix-insightcols ix-vgap">
            <section class="ix-panel" aria-labelledby="contact-h">
                <div class="ix-panel__head"><h2 id="contact-h">Contact actions</h2></div>
                <div class="ix-panel__body">
                    <ul class="ix-meter">
                        @foreach ($contacts as $row)
                            <li><span>{{ $row['label'] }}</span><span class="ix-meter__bar"><span style="width: {{ $contactTotal ? round($row['n'] / $contactTotal * 100) : 0 }}%"></span></span><b>{{ $row['n'] }}</b></li>
                        @endforeach
                    </ul>
                    <p class="ix-hint">Counted when the visitor’s browser reports the tap. A call or email is counted when it is started, not whether it got through.</p>
                </div>
            </section>

            <section class="ix-panel" aria-labelledby="terms-h">
                <div class="ix-panel__head"><h2 id="terms-h">Searches that showed you</h2></div>
                <div class="ix-panel__body">
                    @if (empty($r['terms']))
                        <p class="ix-hint">No search has shown {{ $team->name }} {{ \App\Services\Insights\BusinessInsights::TERM_THRESHOLD }} times yet. Searches show here once they have, so a single person’s words are never on display.</p>
                    @else
                        <ul class="ix-meter">
                            @foreach ($r['terms'] as $term)<li><span>{{ $term['term'] }}</span><span class="ix-meter__bar"><span style="width: {{ round($term['count'] / $r['terms'][0]['count'] * 100) }}%"></span></span><b>{{ $term['count'] }}</b></li>@endforeach
                        </ul>
                    @endif
                    @if ($r['other_terms'] > 0)<p class="ix-hint">Plus {{ $r['other_terms'] }} appearances from searches seen fewer than {{ \App\Services\Insights\BusinessInsights::TERM_THRESHOLD }} times.</p>@endif
                </div>
            </section>

            <section class="ix-panel" aria-labelledby="svc-h">
                <div class="ix-panel__head"><h2 id="svc-h">Services people searched for</h2></div>
                <div class="ix-panel__body">
                    @if (empty($r['services']))
                        <p class="ix-hint">No search has matched one of your listed services yet.</p>
                    @else
                        <ul class="ix-meter">
                            @foreach ($r['services'] as $s)<li><span>{{ $serviceNames[$s['id']] ?? 'A service you removed' }}</span><span class="ix-meter__bar"><span style="width: {{ round($s['count'] / $r['services'][0]['count'] * 100) }}%"></span></span><b>{{ $s['count'] }}</b></li>@endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    @endif
</x-app-layout>
