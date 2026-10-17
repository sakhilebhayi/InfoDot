<x-app-layout>
    {{-- ApexCharts CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>

    @php
        $trendTotal = $solutionTrend->sum() + $questionTrend->sum();
        $scored = ($totalQuestions + $totalSolutions) > 0;
        $solvePct = (int) round($solveRate * 100);
        $livePlatforms = collect($platforms)->filter(fn ($p) => ! empty($p['active']));
        $sortedPlatforms = collect($platforms)->sortByDesc(fn ($p) => ! empty($p['active']) ? 1 : 0)->values();

        $counts = [
            ['label' => 'Users',     'value' => $totalUsers,     'delta' => $usersThisMonth,     'prev' => $usersLastMonth],
            ['label' => 'Solutions', 'value' => $totalSolutions, 'delta' => $solutionsThisMonth, 'prev' => $solutionsLastMonth],
            ['label' => 'Questions', 'value' => $totalQuestions, 'delta' => $questionsThisMonth, 'prev' => $questionsLastMonth],
            ['label' => 'Comments',  'value' => $totalComments,  'delta' => null, 'prev' => null],
            ['label' => 'Teams',     'value' => $totalTeams,     'delta' => null, 'prev' => null],
        ];

        $engines = [
            ['name' => 'Business Intelligence',    'status' => 'active',  'desc' => 'KPIs, dashboards, reports'],
            ['name' => 'Community Intelligence',   'status' => 'active',  'desc' => 'Sentiment, trends, engagement'],
            ['name' => 'People Intelligence',      'status' => 'active',  'desc' => 'Workforce, retention, skills'],
            ['name' => 'Content Intelligence',     'status' => 'active',  'desc' => 'Solutions, Q&A, knowledge'],
            ['name' => 'Operational Intelligence', 'status' => 'planned', 'desc' => 'Fleet, assets, maintenance'],
            ['name' => 'Financial Intelligence',   'status' => 'planned', 'desc' => 'Cash flow, fraud, forecasting'],
            ['name' => 'Customer Intelligence',    'status' => 'planned', 'desc' => 'CLV, churn, satisfaction'],
            ['name' => 'AI Intelligence',          'status' => 'planned', 'desc' => 'Agent ROI, adoption, quality'],
            ['name' => 'Predictive Intelligence',  'status' => 'planned', 'desc' => 'Forecasting, risk, outcomes'],
            ['name' => 'Decision Intelligence',    'status' => 'planned', 'desc' => 'Recommendations, next-best-action'],
            ['name' => 'Risk Intelligence',        'status' => 'planned', 'desc' => 'Threats, compliance, posture'],
            ['name' => 'Document Intelligence',    'status' => 'planned', 'desc' => 'Contracts, expiry, compliance'],
            ['name' => 'Asset Intelligence',       'status' => 'planned', 'desc' => 'Depreciation, lifecycle, ROI'],
            ['name' => 'Mining Intelligence',      'status' => 'planned', 'desc' => 'Production, safety, compliance'],
            ['name' => 'Agriculture Intelligence', 'status' => 'planned', 'desc' => 'Yield, irrigation, equipment'],
        ];
        $activeEngines = collect($engines)->where('status', 'active')->count();
    @endphp

    <style>
        .ixa h2.ix-panel__head { margin: 0; }
        .ixa .ix-state-bar { margin-bottom: 0; }
        .ixa-stamp { font-family: var(--ix-font); font-size: 12px; color: var(--ix-text-3); }
        .ixa-verdict { min-width: 0; }
        .ixa-verdict b { display: block; }
        .ixa-verdict span { display: block; margin-top: 2px; font-weight: 400; font-size: 13px; color: var(--ix-text-2); }
        .ixa-stat-sub { display: block; margin-top: 2px; font-family: var(--ix-font); font-size: 12px; color: var(--ix-text-2); }
        .ixa-stat-sub small { display: block; font-size: 12px; color: var(--ix-text-3); }
        .ixa-trend { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
        @media (max-width: 899px) { .ixa-trend { grid-template-columns: minmax(0, 1fr); } }
        .ixa-legend { display: inline-flex; flex-wrap: wrap; gap: 4px 16px; font-family: var(--ix-font); font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--ix-text-2); }
        .ixa-legend i { display: inline-block; width: 10px; height: 10px; margin-right: 6px; vertical-align: -1px; }
        .ixa-legend .s-sol { background: var(--ix-signal-text); border-radius: 50%; }
        .ixa-legend .s-que { background: var(--ix-text); }
        .ixa-chart { padding: 12px 8px 4px 4px; min-height: 252px; }
        .ixa-none { margin: 0; padding: 20px 16px; color: var(--ix-text-2); max-width: 60ch; text-wrap: pretty; }
        .ixa-eng-label { font-family: var(--ix-font); font-weight: 700; font-size: 24px; line-height: 1.2; }
        .ixa-eng-score { margin-top: 2px; color: var(--ix-text-2); font-size: 13px; }
        .ixa-bar { height: 4px; margin: 12px 0 16px; background: var(--ix-rule); }
        .ixa-bar span { display: block; height: 100%; background: var(--ix-text); }
        .ixa-dl { margin: 0; border-top: 1px solid var(--ix-rule); }
        .ixa-dl > div { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--ix-rule); }
        .ixa-dl dt { color: var(--ix-text-2); font-size: 13px; }
        .ixa-dl dd { margin: 0; font-family: var(--ix-font); font-weight: 500; font-variant-numeric: tabular-nums; }
        .ixa-note { margin: 12px 0 0; font-size: 12px; color: var(--ix-text-3); text-wrap: pretty; }
        .ixa-rows { list-style: none; margin: 0; padding: 0; }
        .ixa-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 11px 16px; border-bottom: 1px solid var(--ix-rule); transition: background-color .15s var(--ix-ease); }
        .ixa-row--av { grid-template-columns: 28px 28px minmax(0, 1fr) auto; }
        .ixa-rows li:last-child .ixa-row { border-bottom: 0; }
        a.ixa-row:hover { background: var(--ix-hover); }
        .ixa-rank { font-family: var(--ix-font); font-size: 13px; color: var(--ix-text-3); }
        .ixa-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
        .ixa-cnt { text-align: right; white-space: nowrap; }
        .ixa-cnt .ix-fig { font-size: 16px; }
        .ixa-cnt small { margin-left: 6px; font-size: 12px; color: var(--ix-text-3); }
        .ixa-bays-wrap { overflow: hidden; }
        .ixa-bays { margin: 0 -1px -1px 0; background: transparent; gap: 0; }
        .ixa-bays .ix-bay { cursor: default; box-shadow: inset -1px -1px 0 var(--ix-rule); }
        .ixa-tbl-wrap { overflow-x: auto; }
        .ixa-tbl { width: 100%; min-width: 560px; border-collapse: collapse; }
        .ixa-tbl th { padding: 9px 16px; text-align: left; background: var(--ix-sunken); border-bottom: 1px solid var(--ix-rule); font-family: var(--ix-font); font-weight: 700; font-size: 13px; letter-spacing: 0; text-transform: none; color: var(--ix-text-3); }
        .ixa-tbl td { padding: 10px 16px; border-bottom: 1px solid var(--ix-rule); vertical-align: middle; }
        .ixa-tbl tr:last-child td { border-bottom: 0; }
        .ixa-tbl td.n { font-weight: 600; }
        .ixa-tbl td.d { color: var(--ix-text-2); }
        .ixa-tbl .ix-state { height: auto; }
        .apexcharts-tooltip { background: var(--ix-panel) !important; border: 1px solid var(--ix-rule-strong) !important; color: var(--ix-text) !important; box-shadow: none !important; border-radius: var(--ix-radius) !important; font-family: var(--ix-font) !important; }
        .apexcharts-tooltip-title { background: var(--ix-sunken) !important; border-bottom: 1px solid var(--ix-rule) !important; color: var(--ix-text) !important; font-family: var(--ix-font) !important; font-size: 12px !important; }
        .apexcharts-tooltip-text, .apexcharts-tooltip-y-group { font-size: 13px !important; }
        .apexcharts-tooltip-text-y-value { font-family: var(--ix-font) !important; }
        .apexcharts-xaxistooltip, .apexcharts-yaxistooltip { background: var(--ix-panel) !important; border-color: var(--ix-rule-strong) !important; color: var(--ix-text) !important; }
        .apexcharts-xaxistooltip-bottom::before, .apexcharts-xaxistooltip-bottom::after { border-bottom-color: var(--ix-rule-strong) !important; }
    </style>

    <div class="ixa">
        <header class="ix-pagehead">
            <div>
                <h1 class="ix-h1">Analytics</h1>
                <p class="ix-lede">How much gets asked, written down and answered across the workspace.</p>
            </div>
            <time class="ixa-stamp" datetime="{{ now()->toIso8601String() }}">{{ now()->format('D d M Y H:i') }}</time>
        </header>

        <div class="ix-stack">

            {{-- State: the verdict in words first, then the figures behind it --}}
            <section class="ix-state-bar" aria-label="Community state">
                <div class="ix-state-bar__verdict">
                    <span class="ix-lamp {{ $engagementScore >= 60 ? 'ix-lamp--ok' : '' }}" aria-hidden="true"></span>
                    <div class="ixa-verdict">
                        @if($scored)
                            <b>Knowledge health is {{ strtolower($engagementLabel) }}.</b>
                            <span>{{ $solvedQuestions }} of {{ $totalQuestions }} {{ \Illuminate\Support\Str::plural('question', $totalQuestions) }} solved, across {{ $totalSolutions }} {{ \Illuminate\Support\Str::plural('solution', $totalSolutions) }}.</span>
                        @else
                            <b>Nothing to score yet.</b>
                            <span>No questions or solutions have been posted, so engagement cannot be measured.</span>
                        @endif
                    </div>
                </div>
                <div class="ix-readouts">
                    <div class="ix-readout">
                        <span class="ix-readout__label">Score / 100</span>
                        <x-ix.figure :value="$engagementScore" :digits="2" />
                    </div>
                    <div class="ix-readout">
                        <span class="ix-readout__label">Solve rate %</span>
                        <x-ix.figure :value="$solvePct" :digits="2" />
                    </div>
                    <div class="ix-readout">
                        <span class="ix-readout__label">Solved</span>
                        <x-ix.figure :value="$solvedQuestions" :digits="2" />
                    </div>
                </div>
            </section>

            {{-- Counts: one ruled readout grid --}}
            <section class="ix-grid ix-grid--6" aria-label="Totals">
                @foreach($counts as $c)
                    <div class="ix-stat">
                        <span class="ix-readout__label">{{ $c['label'] }}</span>
                        <x-ix.figure :value="$c['value']" :digits="2" />
                        @if($c['delta'] !== null)
                            <span class="ixa-stat-sub">+{{ str_pad((string) $c['delta'], 2, '0', STR_PAD_LEFT) }} this month <small>{{ str_pad((string) $c['prev'], 2, '0', STR_PAD_LEFT) }} last month</small></span>
                        @else
                            <span class="ixa-stat-sub"><small>all time</small></span>
                        @endif
                    </div>
                @endforeach
            </section>

            {{-- Trend + engagement --}}
            <section class="ix-grid ixa-trend">
                <div>
                    <h2 class="ix-panel__head">
                        <span>Created, last 6 months</span>
                        <span class="ixa-legend" aria-hidden="true"><span><i class="s-sol"></i>Solutions</span><span><i class="s-que"></i>Questions</span></span>
                    </h2>
                    @if($trendTotal > 0)
                        <div id="activityChart" class="ixa-chart" role="img" aria-label="Line chart of solutions and questions created per month over the last six months. The same values are listed in the table that follows."></div>
                        <table class="ix-sr">
                            <caption>Created per month</caption>
                            <thead><tr><th>Month</th><th>Solutions</th><th>Questions</th></tr></thead>
                            <tbody>
                                @foreach($monthLabels->values() as $i => $label)
                                    <tr><td>{{ $label }}</td><td>{{ $solutionTrend->values()[$i] }}</td><td>{{ $questionTrend->values()[$i] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="ixa-none">No solutions or questions were created in the last six months, so there is no trend to draw. The chart appears once the first one is posted.</p>
                    @endif
                </div>

                <div>
                    <h2 class="ix-panel__head"><span>Knowledge health</span></h2>
                    <div class="ix-panel__body">
                        <div class="ixa-eng-label">{{ $engagementLabel }}</div>
                        <div class="ixa-eng-score">{{ $engagementScore }} of 100</div>
                        <div class="ixa-bar" role="img" aria-label="Knowledge health {{ $engagementScore }} out of 100"><span style="width:{{ $engagementScore }}%"></span></div>
                        <dl class="ixa-dl">
                            <div><dt>Q&amp;A solve rate</dt><dd>{{ $solvePct }}%</dd></div>
                            <div><dt>Replies per question</dt><dd>{{ number_format($avgCommentsPerQ, 1) }}</dd></div>
                                                    </dl>
                        <p class="ixa-note">Weighted 60% solve rate and 40% replies per question (5 or more counts in full).</p>
                    </div>
                </div>
            </section>

            {{-- Rankings --}}
            <section class="ix-grid ix-grid--2">
                <div>
                    <h2 class="ix-panel__head"><span>Most discussed solutions</span><span>Replies</span></h2>
                    @if($topSolutions->isNotEmpty())
                        <ol class="ixa-rows">
                            @foreach($topSolutions as $i => $sol)
                                <li>
                                    <a href="{{ route('solutions.view', $sol->id) }}" class="ixa-row">
                                        <span class="ixa-rank">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="ixa-name">{{ $sol->solution_title }}</span>
                                        <span class="ixa-cnt"><x-ix.figure :value="$sol->comments_count" :digits="2" /></span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="ixa-none">No solutions have been posted yet, so there is nothing to rank.</p>
                    @endif
                </div>

                <div>
                    <h2 class="ix-panel__head"><span>Top contributors</span><span>Questions</span></h2>
                    @if($topContributors->isNotEmpty())
                        <ol class="ixa-rows">
                            @foreach($topContributors as $i => $user)
                                <li>
                                    <div class="ixa-row ixa-row--av">
                                        <span class="ixa-rank">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="ix-avatar"><img src="https://www.gravatar.com/avatar/{{ md5($user->email) }}?d=mp&amp;s=56" width="28" height="28" alt="" loading="lazy"></span>
                                        <span class="ixa-name">{{ $user->name }}</span>
                                        <span class="ixa-cnt"><x-ix.figure :value="$user->questions_count" :digits="2" /></span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="ixa-none">There are no users yet, so no contributors to rank.</p>
                    @endif
                </div>
            </section>

            {{-- Platform coverage + engines share an edge --}}
            <div>
                <section class="ix-panel" aria-labelledby="ixa-platforms">
                    <h2 class="ix-panel__head" id="ixa-platforms">
                        <span>Platform coverage</span>
                        <span class="ixa-stamp">{{ str_pad((string) $livePlatforms->count(), 2, '0', STR_PAD_LEFT) }} live / {{ str_pad((string) count($platforms), 2, '0', STR_PAD_LEFT) }} registered</span>
                    </h2>
                    @if(count($platforms) > 0)
                        <div class="ixa-bays-wrap">
                            <div class="ix-bays ixa-bays">
                                @foreach($sortedPlatforms as $platform)
                                    @php $live = ! empty($platform['active']); @endphp
                                    <div class="ix-bay {{ $live ? 'ix-bay--live' : '' }}">
                                        <span class="ix-bay__text">
                                            <span class="ix-bay__name">{{ $platform['name'] }}</span>
                                            <span class="ix-bay__state">{{ $live ? 'Live' : 'Not live yet' }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="ixa-none">No platforms are registered in this environment's ecosystem configuration.</p>
                    @endif
                </section>

                <section class="ix-panel" aria-labelledby="ixa-engines">
                    <h2 class="ix-panel__head" id="ixa-engines">
                        <span>Intelligence engines</span>
                        <span class="ixa-stamp">{{ str_pad((string) $activeEngines, 2, '0', STR_PAD_LEFT) }} active / {{ str_pad((string) count($engines), 2, '0', STR_PAD_LEFT) }}</span>
                    </h2>
                    <div class="ixa-tbl-wrap">
                        <table class="ixa-tbl">
                            <caption class="ix-sr">Each engine reads data from connected Dot platforms and produces cross-platform intelligence.</caption>
                            <thead><tr><th scope="col">Engine</th><th scope="col">Covers</th><th scope="col">State</th></tr></thead>
                            <tbody>
                                @foreach($engines as $engine)
                                    <tr>
                                        <td class="n">{{ $engine['name'] }}</td>
                                        <td class="d">{{ $engine['desc'] }}</td>
                                        <td><span class="ix-state {{ $engine['status'] === 'active' ? 'ix-state--done' : '' }}">{{ $engine['status'] === 'active' ? 'Active' : 'Planned' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

        </div>
    </div>

    @if($trendTotal > 0)
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('activityChart')
        if (!el || typeof ApexCharts === 'undefined') return

        const solutions = @json($solutionTrend->values());
        const questions = @json($questionTrend->values());
        const months = @json($monthLabels->values());
        const top = Math.max(1, ...solutions, ...questions)

        const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()
        const themed = () => {
            const text = token('--ix-text')
            return {
                colors: [token('--ix-signal-text'), text],
                chart: { foreColor: token('--ix-text-3') },
                grid: { borderColor: token('--ix-rule') },
                markers: { strokeColors: token('--ix-panel') },
                xaxis: { labels: { style: { colors: token('--ix-text-3') } } },
                yaxis: { labels: { style: { colors: token('--ix-text-3') } } },
                tooltip: { theme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light' },
            }
        }

        const base = themed()
        const font = 'IBM Plex Sans'
        const chart = new ApexCharts(el, {
            chart: {
                type: 'line',
                height: 240,
                fontFamily: font,
                foreColor: base.chart.foreColor,
                background: 'transparent',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: false },
            },
            series: [
                { name: 'Solutions', data: solutions },
                { name: 'Questions', data: questions },
            ],
            colors: base.colors,
            stroke: { curve: 'straight', width: 2 },
            fill: { type: 'solid', opacity: 1 },
            markers: { size: 4, shape: ['circle', 'square'], strokeWidth: 2, strokeColors: base.markers.strokeColors, hover: { sizeOffset: 2 } },
            xaxis: {
                categories: months,
                labels: { style: { colors: base.xaxis.labels.style.colors, fontSize: '12px', fontFamily: font } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                min: 0,
                max: top <= 5 ? top : undefined,
                tickAmount: top <= 5 ? top : 5,
                forceNiceScale: top > 5,
                labels: {
                    style: { colors: base.yaxis.labels.style.colors, fontSize: '12px', fontFamily: 'IBM Plex Mono' },
                    formatter: (v) => String(Math.round(v)),
                },
            },
            grid: { borderColor: base.grid.borderColor, strokeDashArray: 0, padding: { left: 4, right: 12 } },
            tooltip: { theme: base.tooltip.theme, shared: true, intersect: false, style: { fontSize: '13px', fontFamily: font } },
            legend: { show: false },
            dataLabels: { enabled: false },
        })
        chart.render()

        // Re-read the tokens when the operating system flips between night and light.
        const mq = window.matchMedia('(prefers-color-scheme: dark)')
        const onScheme = () => {
            const t = themed()
            chart.updateOptions({
                colors: t.colors,
                chart: { foreColor: t.chart.foreColor },
                grid: { borderColor: t.grid.borderColor },
                markers: { strokeColors: t.markers.strokeColors },
                xaxis: { labels: { style: { colors: t.xaxis.labels.style.colors } } },
                yaxis: { labels: { style: { colors: t.yaxis.labels.style.colors } } },
                tooltip: { theme: t.tooltip.theme },
            })
        }
        if (mq.addEventListener) mq.addEventListener('change', () => setTimeout(onScheme, 0))
    })
    </script>
    @endif

</x-app-layout>
