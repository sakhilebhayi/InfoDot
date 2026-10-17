<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#b6c4ff]">Dot.Analytics</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Intelligence Dashboard</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full border border-[#22c55e]/30 bg-[#22c55e]/10 px-4 py-1.5 text-xs font-bold text-[#22c55e]">
                    Live
                </span>
                <span class="text-xs text-[#8d90a2]">{{ now()->format('D, d M Y · H:i') }}</span>
            </div>
        </div>
    </x-slot>

    {{-- ApexCharts CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>

    <div class="px-6 pb-14 pt-6 lg:px-10" style="font-family:'Inter',sans-serif;">
        <div class="mx-auto max-w-7xl space-y-8">

            {{-- ══════════════════════════════════════════════════════════════
                 ROW 1 · Headline KPI cards
            ══════════════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">

                @php
                    $kpis = [
                        ['label' => 'Users',       'value' => $totalUsers,     'icon' => 'group',       'delta' => $usersThisMonth,     'color' => '#2962ff'],
                        ['label' => 'Solutions',   'value' => $totalSolutions, 'icon' => 'lightbulb',   'delta' => $solutionsThisMonth, 'color' => '#7c3aed'],
                        ['label' => 'Questions',   'value' => $totalQuestions, 'icon' => 'help_outline', 'delta' => $questionsThisMonth, 'color' => '#0891b2'],
                        ['label' => 'Comments',    'value' => $totalComments,  'icon' => 'chat_bubble',  'delta' => null,               'color' => '#059669'],
                        ['label' => 'Likes',       'value' => $totalLikes,     'icon' => 'favorite',    'delta' => null,               'color' => '#dc2626'],
                        ['label' => 'Teams',       'value' => $totalTeams,     'icon' => 'groups',      'delta' => null,               'color' => '#d97706'],
                    ];
                @endphp

                @foreach($kpis as $kpi)
                    <div class="rounded-2xl border border-[#434656]/25 p-5" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                        <div class="flex items-start justify-between">
                            <div class="rounded-xl p-2" style="background:{{ $kpi['color'] }}18;">
                                <span class="material-symbols-rounded" style="font-size:20px;color:{{ $kpi['color'] }};">{{ $kpi['icon'] }}</span>
                            </div>
                            @if($kpi['delta'] !== null && $kpi['delta'] > 0)
                                <span class="rounded-full bg-[#22c55e]/10 px-2 py-0.5 text-[10px] font-bold text-[#22c55e]">+{{ $kpi['delta'] }}</span>
                            @endif
                        </div>
                        <div class="mt-4">
                            <p class="text-2xl font-extrabold tracking-tight text-[#dae2fd]">{{ number_format($kpi['value']) }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-widest text-[#8d90a2]">{{ $kpi['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ══════════════════════════════════════════════════════════════
                 ROW 2 · Activity trend chart + Engagement score
            ══════════════════════════════════════════════════════════════ --}}
            <div class="grid gap-6 lg:grid-cols-3">

                {{-- Activity trend (ApexCharts) --}}
                <div class="rounded-2xl border border-[#434656]/25 p-6 lg:col-span-2" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#8d90a2]">Community Activity</p>
                            <h3 class="mt-1 text-base font-bold text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">6-Month Content Trend</h3>
                        </div>
                        <div class="flex items-center gap-4 text-xs">
                            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-4 rounded-full bg-[#2962ff]"></span> Solutions</span>
                            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-4 rounded-full bg-[#7c3aed]"></span> Questions</span>
                        </div>
                    </div>
                    <div id="activityChart"></div>
                </div>

                {{-- Engagement score ring --}}
                <div class="rounded-2xl border border-[#434656]/25 p-6" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#8d90a2]">Community Intelligence</p>
                    <h3 class="mt-1 text-base font-bold text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Engagement Score</h3>

                    <div class="relative mt-4 flex justify-center">
                        <div id="engagementRing"></div>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-extrabold text-[#dae2fd]">{{ $engagementScore }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-widest text-[#8d90a2]">/ 100</span>
                        </div>
                    </div>

                    <p class="mt-2 text-center text-sm font-bold text-[#b6c4ff]">{{ $engagementLabel }}</p>

                    <div class="mt-5 space-y-3">
                        @php
                            $metrics = [
                                ['label' => 'Q&A Solve Rate',        'value' => round($solveRate * 100) . '%',       'color' => '#22c55e'],
                                ['label' => 'Avg Comments / Q',      'value' => number_format($avgCommentsPerQ, 1),  'color' => '#2962ff'],
                                ['label' => 'Social Connections',    'value' => number_format($totalFollows),        'color' => '#7c3aed'],
                            ];
                        @endphp
                        @foreach($metrics as $m)
                            <div class="flex items-center justify-between rounded-xl bg-[#0b1326]/60 px-4 py-2.5">
                                <span class="text-xs text-[#b7c8e1]">{{ $m['label'] }}</span>
                                <span class="text-sm font-bold" style="color:{{ $m['color'] }};">{{ $m['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════
                 ROW 3 · Content Intelligence + Top Contributors
            ══════════════════════════════════════════════════════════════ --}}
            <div class="grid gap-6 lg:grid-cols-2">

                {{-- Top Solutions --}}
                <div class="rounded-2xl border border-[#434656]/25 p-6" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                    <div class="mb-5 flex items-center gap-3">
                        <div class="rounded-xl bg-[#2962ff]/15 p-2">
                            <span class="material-symbols-rounded text-[#2962ff]" style="font-size:20px;">workspace_premium</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#8d90a2]">Content Intelligence</p>
                            <h3 class="text-sm font-bold text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Top Solutions by Engagement</h3>
                        </div>
                    </div>

                    @forelse($topSolutions as $i => $sol)
                        <a href="{{ route('solutions.view', $sol->id) }}"
                           class="flex items-center gap-4 rounded-xl px-4 py-3 transition hover:bg-[#2962ff]/08 mb-2 last:mb-0">
                            <span class="text-lg font-extrabold text-[#434656]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-[#dae2fd]">{{ $sol->solution_title }}</p>
                            </div>
                            <div class="flex items-center gap-1 text-xs text-[#dc2626]">
                                <span class="material-symbols-rounded" style="font-size:14px;fill:1;">favorite</span>
                                <span class="font-bold">{{ $sol->likes_count }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="py-8 text-center text-sm text-[#8d90a2]">No solutions yet — create the first one.</p>
                    @endforelse
                </div>

                {{-- Top Contributors --}}
                <div class="rounded-2xl border border-[#434656]/25 p-6" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                    <div class="mb-5 flex items-center gap-3">
                        <div class="rounded-xl bg-[#7c3aed]/15 p-2">
                            <span class="material-symbols-rounded text-[#7c3aed]" style="font-size:20px;">military_tech</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#8d90a2]">People Intelligence</p>
                            <h3 class="text-sm font-bold text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Top Contributors</h3>
                        </div>
                    </div>

                    @forelse($topContributors as $i => $user)
                        <div class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 last:mb-0">
                            <img src="https://www.gravatar.com/avatar/{{ md5($user->email) }}?d=mp&s=36"
                                 class="h-9 w-9 rounded-full border border-[#434656]/40 object-cover flex-shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-[#dae2fd]">{{ $user->name }}</p>
                                <p class="text-[11px] text-[#8d90a2]">{{ $user->questions_count }} questions</p>
                            </div>
                            @if($i === 0)
                                <span class="rounded-full bg-[#d97706]/15 px-2.5 py-1 text-[10px] font-bold text-[#d97706]">Top</span>
                            @endif
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-[#8d90a2]">No contributors yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════
                 ROW 4 · Ecosystem Intelligence · Platform coverage
            ══════════════════════════════════════════════════════════════ --}}
            <div class="rounded-2xl border border-[#434656]/25 p-6" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#8d90a2]">Ecosystem Intelligence</p>
                        <h3 class="mt-1 text-base font-bold text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Platform Coverage</h3>
                    </div>
                    <span class="rounded-full border border-[#2962ff]/30 bg-[#2962ff]/10 px-4 py-1.5 text-xs font-bold text-[#b6c4ff]">
                        {{ count($platforms) }} platforms registered
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-3 sm:grid-cols-6 lg:grid-cols-9">
                    @foreach($platforms as $key => $platform)
                        <div class="flex flex-col items-center gap-2 rounded-2xl border border-[#434656]/20 bg-[#0b1326]/50 p-3 text-center">
                            <span class="material-symbols-rounded text-[#b6c4ff]" style="font-size:22px;">{{ $platform['icon'] }}</span>
                            <span class="w-full truncate text-[0.58rem] font-bold uppercase tracking-wide text-[#8d90a2]">
                                {{ Str::after($platform['name'], '.') }}
                            </span>
                            <span class="h-1.5 w-1.5 rounded-full bg-[#22c55e]" title="Online"></span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════
                 ROW 5 · Intelligence Engines (coming soon)
            ══════════════════════════════════════════════════════════════ --}}
            <div class="rounded-2xl border border-[#434656]/25 p-6" style="background:rgba(19,27,46,0.9);backdrop-filter:blur(16px);">
                <div class="mb-6">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#8d90a2]">Intelligence Engines</p>
                    <h3 class="mt-1 text-base font-bold text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Powered by Dot.Analytics</h3>
                    <p class="mt-1 text-xs text-[#8d90a2]">Each engine consumes data from connected Dot platforms and produces cross-platform intelligence.</p>
                </div>

                @php
                    $engines = [
                        ['name' => 'Business Intelligence',    'icon' => 'bar_chart',       'status' => 'active',  'desc' => 'KPIs · dashboards · reports'],
                        ['name' => 'Community Intelligence',   'icon' => 'groups',          'status' => 'active',  'desc' => 'Sentiment · trends · engagement'],
                        ['name' => 'People Intelligence',      'icon' => 'person_pin',      'status' => 'active',  'desc' => 'Workforce · retention · skills'],
                        ['name' => 'Content Intelligence',     'icon' => 'article',         'status' => 'active',  'desc' => 'Solutions · Q&A · knowledge'],
                        ['name' => 'Operational Intelligence', 'icon' => 'precision_manufacturing', 'status' => 'planned', 'desc' => 'Fleet · assets · maintenance'],
                        ['name' => 'Financial Intelligence',   'icon' => 'account_balance', 'status' => 'planned', 'desc' => 'Cash flow · fraud · forecasting'],
                        ['name' => 'Customer Intelligence',    'icon' => 'support_agent',   'status' => 'planned', 'desc' => 'CLV · churn · satisfaction'],
                        ['name' => 'AI Intelligence',          'icon' => 'smart_toy',       'status' => 'planned', 'desc' => 'Agent ROI · adoption · quality'],
                        ['name' => 'Predictive Intelligence',  'icon' => 'trending_up',     'status' => 'planned', 'desc' => 'Forecasting · risk · outcomes'],
                        ['name' => 'Decision Intelligence',    'icon' => 'psychology',      'status' => 'planned', 'desc' => 'Recommendations · next-best-action'],
                        ['name' => 'Risk Intelligence',        'icon' => 'security',        'status' => 'planned', 'desc' => 'Threats · compliance · posture'],
                        ['name' => 'Document Intelligence',    'icon' => 'description',     'status' => 'planned', 'desc' => 'Contracts · expiry · compliance'],
                        ['name' => 'Asset Intelligence',       'icon' => 'construction',    'status' => 'planned', 'desc' => 'Depreciation · lifecycle · ROI'],
                        ['name' => 'Mining Intelligence',      'icon' => 'terrain',         'status' => 'planned', 'desc' => 'Production · safety · compliance'],
                        ['name' => 'Agriculture Intelligence', 'icon' => 'grass',           'status' => 'planned', 'desc' => 'Yield · irrigation · equipment'],
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach($engines as $engine)
                        @php $active = $engine['status'] === 'active'; @endphp
                        <div class="rounded-2xl border p-4 {{ $active ? 'border-[#2962ff]/30 bg-[#2962ff]/05' : 'border-[#434656]/20 bg-[#0b1326]/40' }}">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="material-symbols-rounded {{ $active ? 'text-[#2962ff]' : 'text-[#434656]' }}" style="font-size:22px;">{{ $engine['icon'] }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide {{ $active ? 'bg-[#22c55e]/15 text-[#22c55e]' : 'bg-[#434656]/20 text-[#8d90a2]' }}">
                                    {{ $active ? 'Active' : 'Planned' }}
                                </span>
                            </div>
                            <p class="text-xs font-bold leading-tight {{ $active ? 'text-[#dae2fd]' : 'text-[#8d90a2]' }}">{{ $engine['name'] }}</p>
                            <p class="mt-1 text-[10px] text-[#434656]">{{ $engine['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    {{-- ── ApexCharts init ─────────────────────────────────────────────── --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const chartColors = {
            bg:     '#0b1326',
            grid:   'rgba(67,70,86,0.15)',
            text:   '#8d90a2',
            blue:   '#2962ff',
            purple: '#7c3aed',
        }

        // Activity trend – area chart
        const activityOptions = {
            chart: {
                type: 'area',
                height: 220,
                background: 'transparent',
                toolbar: { show: false },
                sparkline: { enabled: false },
                animations: { enabled: true, speed: 600 },
            },
            theme: { mode: 'dark' },
            series: [
                {
                    name: 'Solutions',
                    data: {!! $solutionTrend->values()->toJson() !!},
                },
                {
                    name: 'Questions',
                    data: {!! $questionTrend->values()->toJson() !!},
                },
            ],
            colors: [chartColors.blue, chartColors.purple],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.02,
                    stops: [0, 100],
                },
            },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories: {!! $monthLabels->values()->toJson() !!},
                labels: { style: { colors: chartColors.text, fontSize: '11px' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { colors: chartColors.text, fontSize: '11px' },
                    formatter: val => Math.round(val),
                },
                min: 0,
            },
            grid: {
                borderColor: chartColors.grid,
                strokeDashArray: 4,
                padding: { left: 0, right: 0 },
            },
            tooltip: {
                theme: 'dark',
                style: { fontFamily: 'Inter, sans-serif' },
            },
            legend: { show: false },
            dataLabels: { enabled: false },
        }

        new ApexCharts(document.getElementById('activityChart'), activityOptions).render()

        // Engagement ring – radial bar
        const ringOptions = {
            chart: {
                type: 'radialBar',
                height: 200,
                background: 'transparent',
                toolbar: { show: false },
            },
            theme: { mode: 'dark' },
            series: [{{ $engagementScore }}],
            colors: ['#2962ff'],
            plotOptions: {
                radialBar: {
                    hollow: { size: '62%' },
                    track: { background: 'rgba(67,70,86,0.2)', strokeWidth: '100%' },
                    dataLabels: { show: false },
                    startAngle: -135,
                    endAngle: 135,
                },
            },
            stroke: { lineCap: 'round' },
        }

        new ApexCharts(document.getElementById('engagementRing'), ringOptions).render()
    })
    </script>

    @include('layouts.footer')
</x-app-layout>
