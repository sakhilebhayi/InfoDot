<x-app-layout title="Growth Centre">
    <nav class="ix-mod__tabs" aria-label="Growth Centre views">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.growth', ['tab' => $key, 'from' => $from, 'to' => $to]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Growth Centre</h1>
            <p class="ix-lede">Every figure is counted from records InfoDot keeps. Where InfoDot cannot know a figure yet, it says so.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="ix-note" role="status"><p>{{ session('status') }}</p></div>
    @endif

    @if (in_array($tab, ['overview', 'funnel', 'channels'], true))
        <form method="GET" class="ix-filters" aria-label="Date range">
            <label>From <input class="ix-input" type="date" name="from" value="{{ $from }}"></label>
            <label>To <input class="ix-input" type="date" name="to" value="{{ $to }}"></label>
            <button class="ix-btn ix-btn--ghost">Apply</button>
        </form>
    @endif

    @if ($tab === 'overview')
        <section class="ix-panel" aria-label="Overview">
            <div class="ix-panel__body">
                <dl class="ix-biz__facts">
                    <div><dt>Accounts</dt><dd><strong>{{ number_format($overview['accounts']) }}</strong> in all, {{ number_format($overview['new_accounts']) }} in the range shown</dd></div>
                    <div><dt>Email confirmed</dt><dd><strong>{{ number_format($overview['verified']) }}</strong></dd></div>
                    <div><dt>Businesses</dt><dd><strong>{{ number_format($overview['businesses']) }}</strong> in all, {{ number_format($overview['new_businesses']) }} in the range shown</dd></div>
                    <div><dt>People who opened a platform</dt><dd><strong>{{ number_format($overview['platform_openers']) }}</strong></dd></div>
                </dl>
            </div>
        </section>
        <section class="ix-panel ix-vgap" aria-label="Not yet measured">
            <div class="ix-panel__head"><h2>Not yet measured</h2></div>
            <div class="ix-panel__body">
                <dl class="ix-biz__facts">
                    @foreach ($unmeasured as $name => $why)
                        <div><dt>{{ $name }}</dt><dd>Not yet measured. {{ $why }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>
    @elseif ($tab === 'funnel')
        <section class="ix-panel ix-panel--clip" aria-label="Onboarding funnel">
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Step</th><th class="num">People</th><th>Follows</th><th class="num">Share of that step</th><th class="num">Share of all who signed up</th></tr></thead>
                    <tbody>
                        @foreach ($funnel as $row)
                            <tr>
                                <td class="title">{{ $row['label'] }}</td>
                                <td class="num">{{ number_format($row['people']) }}</td>
                                <td class="muted">{{ $row['parent'] ?? 'None' }}</td>
                                <td class="num">{{ $row['of_parent'] === null ? 'None' : $row['of_parent'].'%' }}</td>
                                <td class="num">{{ $row['of_first'] === null ? 'None' : $row['of_first'].'%' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <p class="ix-hint">Steps are counted from the day recording began. People who joined earlier are not in these counts.</p>

        {{-- R-73: which homepage button people signed up from, and whether they then got going. People, not clicks. --}}
        <section class="ix-panel ix-panel--clip ix-vgap" aria-label="Sign-ups by homepage button">
            <div class="ix-panel__head"><h2>Sign-ups by homepage button</h2></div>
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Came from</th><th class="num">Accounts</th>@foreach (\App\Services\Growth\OnboardingFunnel::SOURCE_STEPS as $step)<th class="num">{{ config('onboarding.steps.'.$step) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($sources as $row)
                            <tr><td class="title">{{ $row['label'] }}</td><td class="num">{{ number_format($row['accounts']) }}</td>@foreach ($row['steps'] as $people)<td class="num">{{ number_format($people) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <p class="ix-hint">Recorded from the homepage's two "Join InfoDot for free" buttons since R-73. "Anywhere else" is every other way people signed up.</p>

        <section class="ix-panel ix-panel--clip ix-vgap" aria-label="How companies were set up">
            <div class="ix-panel__head"><h2>How companies were set up</h2></div>
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Way</th><th class="num">Started</th><th class="num">Confirmed</th><th class="num">Still open</th></tr></thead>
                    <tbody>
                        @foreach ($capture as $row)
                            <tr><td class="title">{{ ['manual' => 'Typed by hand', 'website' => 'Read from a website', 'document' => 'Read from a document'][$row['source']] }}</td><td class="num">{{ number_format($row['started']) }}</td><td class="num">{{ number_format($row['confirmed']) }}</td><td class="num">{{ number_format($row['open']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @if ($insights)
            <section class="ix-panel ix-panel--clip ix-vgap" aria-label="Where people stopped">
                <div class="ix-panel__head"><h2>Where people stopped</h2></div>
                <div class="ix-tablewrap">
                    <table class="ix-table ix-adm__table">
                        <thead><tr><th>Last step reached</th><th class="num">People</th></tr></thead>
                        <tbody>@foreach ($insights['stopped'] as $row)<tr><td class="title">{{ $row['label'] }}</td><td class="num">{{ number_format($row['people']) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </section>
            <p class="ix-hint">"Stopped" means the last step so far. Someone counted here may still carry on.</p>

            <section class="ix-panel ix-vgap" aria-label="Time to first company">
                <div class="ix-panel__head"><h2>Time from account to first company</h2></div>
                <div class="ix-panel__body">
                    @if ($insights['time']['median_minutes'] === null)
                        <p>Not yet. No one in this range has created a company.</p>
                    @else
                        @php($m = $insights['time']['median_minutes'])
                        <p>The middle time is <strong>{{ $m < 60 ? $m.' minutes' : ($m < 2880 ? round($m / 60, 1).' hours' : round($m / 1440, 1).' days') }}</strong>, from {{ number_format($insights['time']['people']) }} {{ \Illuminate\Support\Str::plural('person', $insights['time']['people']) }}.</p>
                    @endif
                </div>
            </section>

            <section class="ix-panel ix-panel--clip ix-vgap" aria-label="Reading a website">
                <div class="ix-panel__head"><h2>What reading a website found</h2></div>
                @if ($insights['website'] === [])
                    <div class="ix-panel__body"><p>Not yet. No company has been set up from a website.</p></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table ix-adm__table">
                            <thead><tr><th>Field</th><th class="num">Found</th><th class="num">Kept as found</th><th class="num">Changed by the person</th></tr></thead>
                            <tbody>@foreach ($insights['website'] as $row)<tr><td class="title">{{ str_replace('_', ' ', $row['field']) }}</td><td class="num">{{ number_format($row['found']) }}</td><td class="num">{{ number_format($row['kept']) }}</td><td class="num">{{ number_format($row['changed']) }}</td></tr>@endforeach</tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="ix-panel ix-panel--clip ix-vgap" aria-label="People coming back">
                <div class="ix-panel__head"><h2>People coming back</h2></div>
                @if ($insights['retention']['since'] === null)
                    <div class="ix-panel__body"><p>Not yet. Sign-in days have not been recorded long enough.</p></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table ix-adm__table">
                            <thead><tr><th>Signed in again at least</th><th class="num">Accounts old enough</th><th class="num">Did</th><th class="num">Share</th></tr></thead>
                            <tbody>@foreach ($insights['retention']['windows'] as $w)<tr><td class="title">{{ $w['days'] }} {{ \Illuminate\Support\Str::plural('day', $w['days']) }} after signing up</td><td class="num">{{ number_format($w['cohort']) }}</td><td class="num">{{ number_format($w['returned']) }}</td><td class="num">{{ $w['share'] === null ? 'Not yet' : $w['share'].'%' }}</td></tr>@endforeach</tbody>
                        </table>
                    </div>
                    <p class="ix-hint">Counted from the days people signed in, recorded since {{ $insights['retention']['since'] }}. Only accounts made since then are included, and a person who stays signed in without signing in again is not counted.</p>
                @endif
            </section>
        @endif

    @elseif ($tab === 'channels')
        <section class="ix-panel ix-panel--clip" aria-label="How people arrived">
            @if ($channels['rows'] === [])
                <p class="ix-hint ix-panel__body">No arrivals have been recorded in this range yet.</p>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table ix-adm__table">
                        <thead><tr><th>Channel</th><th class="num">People</th><th class="num">Share of recorded arrivals</th></tr></thead>
                        <tbody>
                            @foreach ($channels['rows'] as $row)
                                <tr><td class="title">{{ ucfirst(str_replace('_', ' ', $row['channel'])) }}</td><td class="num">{{ number_format($row['people']) }}</td><td class="num">{{ $row['share'] }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        <p class="ix-hint">{{ number_format($channels['unknown']) }} {{ $channels['unknown'] === 1 ? 'account has' : 'accounts have' }} no recorded arrival and {{ $channels['unknown'] === 1 ? 'is' : 'are' }} not placed in any channel.</p>
    @elseif ($tab === 'geography')
        <section class="ix-panel ix-panel--clip" aria-label="Where businesses are registered">
            @if ($geography['rows'] === [])
                <p class="ix-hint ix-panel__body">No business has stated where it is registered yet.</p>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table ix-adm__table">
                        <thead><tr><th>Country</th><th class="num">Businesses</th></tr></thead>
                        <tbody>
                            @foreach ($geography['rows'] as $row)
                                <tr><td class="title">{{ $row['country'] }}</td><td class="num">{{ number_format($row['businesses']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        <p class="ix-hint">{{ number_format($geography['unstated']) }} {{ $geography['unstated'] === 1 ? 'business has' : 'businesses have' }} not stated a country.</p>
    @elseif ($tab === 'platforms')
        <section class="ix-panel ix-panel--clip" aria-label="Platforms in use">
            @if ($platforms === [])
                <p class="ix-hint ix-panel__body">No business holds a platform yet.</p>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table ix-adm__table">
                        <thead><tr><th>Platform</th><th class="num">Businesses holding it</th></tr></thead>
                        <tbody>
                            @foreach ($platforms as $row)
                                <tr><td class="title">{{ config('ecosystem.platforms.'.$row['platform'].'.name', $row['platform']) }}</td><td class="num">{{ number_format($row['businesses']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @elseif ($tab === 'referrals')
        <section class="ix-panel" aria-label="Referrals">
            <div class="ix-panel__body">
                <dl class="ix-biz__facts">
                    @foreach (['registered' => 'Registered', 'verified' => 'Verified', 'activated' => 'Activated', 'reversed' => 'Reversed', 'refused' => 'Refused'] as $key => $label)
                        <div><dt>{{ $label }}</dt><dd><strong>{{ number_format($referrals['states'][$key] ?? 0) }}</strong></dd></div>
                    @endforeach
                    @foreach (['held' => 'Rewards held', 'due' => 'Rewards due', 'sent' => 'Rewards sent', 'reversed' => 'Rewards reversed'] as $key => $label)
                        <div><dt>{{ $label }}</dt><dd><strong>{{ number_format($referrals['rewards'][$key] ?? 0) }}</strong></dd></div>
                    @endforeach
                </dl>
                <p class="ix-hint">Rewards are not sent to the points ledger until the Dot.Dopemine contract is confirmed; due rewards wait.</p>
            </div>
        </section>
        <form method="POST" action="{{ route('admin.growth.tiers') }}" class="ix-panel ix-vgap">
            @csrf
            <div class="ix-panel__head"><h2>Reward tiers</h2></div>
            <div class="ix-panel__body">
                @foreach ($referrals['tiers'] as $tier)
                    <div class="ix-field">
                        <label for="tier-{{ $tier->id }}">{{ $tier->referrals_required }} activated referrals earn (points)</label>
                        <input id="tier-{{ $tier->id }}" class="ix-input" type="number" min="1" name="tiers[{{ $tier->id }}][points]" value="{{ $tier->points }}" required>
                        <label><input type="checkbox" name="tiers[{{ $tier->id }}][active]" value="1" @checked($tier->active)> On</label>
                    </div>
                @endforeach
                <button class="ix-btn ix-btn--primary">Save tiers</button>
            </div>
        </form>
    @else
        <form method="POST" action="{{ route('admin.growth.targets') }}" class="ix-panel">
            @csrf
            <div class="ix-panel__head"><h2>Targets</h2></div>
            <div class="ix-panel__body">
                <p class="ix-hint">A target is a plan. It is shown beside the figure counted today and never replaces it. Leave a value empty to remove the target.</p>
                @foreach ($targets as $metric => $t)
                    <div class="ix-field">
                        <label for="t-{{ $metric }}">{{ $t['label'] }}: {{ number_format($t['actual']) }} today</label>
                        <input id="t-{{ $metric }}" class="ix-input" type="number" min="1" name="targets[{{ $metric }}][value]" value="{{ $t['target'] }}" placeholder="No target">
                        <input class="ix-input" type="date" name="targets[{{ $metric }}][date]" value="{{ $t['target_date'] ? \Illuminate\Support\Carbon::parse($t['target_date'])->toDateString() : '' }}" aria-label="Target date for {{ $t['label'] }}">
                        @if ($t['progress'] !== null)<p class="ix-hint">{{ $t['progress'] }}% of the way{{ $t['target_date'] ? ', for '.$t['target_date'] : '' }}.</p>@endif
                    </div>
                @endforeach
                <button class="ix-btn ix-btn--primary">Save targets</button>
            </div>
        </form>
    @endif
</x-app-layout>
