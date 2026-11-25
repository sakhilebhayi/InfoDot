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
