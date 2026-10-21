<x-app-layout>
    @php
        $notice = session('points_notice');
        $date = fn ($value) => \Illuminate\Support\Carbon::parse($value)->format('j M Y');
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Dot Points</h1>
            <p class="ix-lede">Points come from answers other people find useful and from solutions that are accepted or verified. Spend them on the rewards below.</p>
        </div>
    </div>

    @if ($notice)
        <div class="ix-pts__notice ix-pts__notice--{{ $notice['level'] === 'ok' ? 'ok' : 'error' }}" role="{{ $notice['level'] === 'ok' ? 'status' : 'alert' }}">
            <span class="material-symbols-rounded" aria-hidden="true">{{ $notice['level'] === 'ok' ? 'check_circle' : 'error' }}</span>
            <p>{{ $notice['message'] }}</p>
        </div>
    @endif

    @if ($state !== 'ok')
        <section class="ix-pts__down" aria-labelledby="pts-down">
            <h2 id="pts-down">Points are unavailable right now</h2>
            @if ($state === 'not_configured')
                <p>The points service is not connected to InfoDot yet, so there is no balance or catalogue to show. Nothing is lost: your points are kept and will appear here once it is.</p>
            @else
                <p>InfoDot could not reach the points service. Your points are not lost and nothing has been spent. Try this page again in a few minutes.</p>
            @endif
            <p><a href="{{ route('points') }}" class="ix-btn">Try again</a></p>
        </section>
    @else
        <div class="ix-pts__top">
            <section class="ix-pts__box" aria-labelledby="pts-balance">
                <h2 id="pts-balance">Your balance</h2>
                @if ($summaryDown)
                    <p class="ix-pts__muted">Your balance is unavailable right now. It is not lost.</p>
                @else
                    <p class="ix-pts__balance"><span class="ix-stencil-num">{{ number_format($summary['balance']) }}</span> <span>points you can redeem</span></p>
                    @isset($summary['total_earned'])
                        <p class="ix-pts__muted">{{ number_format($summary['total_earned']) }} earned in total.</p>
                    @endisset
                @endif
            </section>

            <section class="ix-pts__box" aria-labelledby="pts-expiry">
                <h2 id="pts-expiry">About to lapse</h2>
                @if ($summaryDown)
                    <p class="ix-pts__muted">Expiry dates are unavailable right now.</p>
                @else
                    @php($soon = $summary['expiring_soon'] ?? null)
                    @if (! empty($soon['points']))
                        <p class="ix-pts__warn"><strong>{{ number_format($soon['points']) }} points</strong> lapse within 30 days{{ ! empty($soon['first_at']) ? ', the first of them on '.$date($soon['first_at']) : '' }}.</p>
                    @endif
                    @if (! empty($summary['next_expiry']))
                        <p>
                            @if (! empty($summary['next_expiry_points']))
                                Next to lapse: <strong>{{ number_format($summary['next_expiry_points']) }} points</strong> on {{ $date($summary['next_expiry']) }}.
                            @else
                                Next points lapse on {{ $date($summary['next_expiry']) }}.
                            @endif
                        </p>
                    @elseif (empty($soon['points']))
                        <p class="ix-pts__muted">You hold no points that can lapse.</p>
                    @endif
                    <p class="ix-pts__muted">Points are spent oldest first, so the ones closest to lapsing go first.</p>
                @endif
            </section>
        </div>

        <section class="ix-pts__section" aria-labelledby="pts-rewards">
            <h2 id="pts-rewards">Rewards</h2>
            @if ($catalogueDown)
                <p class="ix-pts__muted">The reward catalogue is unavailable right now. Try again in a few minutes.</p>
            @elseif ($catalogue === [])
                <p class="ix-pts__muted">There is nothing to redeem yet. Rewards appear here when they are set up.</p>
            @else
                @foreach ($catalogue as $kind => $entries)
                    <div class="ix-pts__kind" role="group" aria-labelledby="pts-kind-{{ $kind }}">
                        <h3 id="pts-kind-{{ $kind }}">{{ \App\Services\Points\Reward::kindLabel($kind) }}</h3>
                        <ul class="ix-pts__list">
                            @foreach ($entries as $entry)
                                @php($reward = $entry['reward'])
                                <li class="ix-pts__item">
                                    <div class="ix-pts__what">
                                        <h4>{{ $reward->name }}</h4>
                                        @if ($reward->description)
                                            <p>{{ $reward->description }}</p>
                                        @endif
                                        <p class="ix-pts__meta">
                                            <span class="ix-pts__cost">{{ number_format($reward->costPoints) }} points</span>
                                            @if (! $reward->available)
                                                <span class="ix-pts__tag">Unavailable right now</span>
                                            @elseif ($balance !== null && $balance < $reward->costPoints)
                                                <span class="ix-pts__tag">You need {{ number_format($reward->costPoints - $balance) }} more</span>
                                            @else
                                                <span class="ix-pts__tag ix-pts__tag--ok">Available</span>
                                            @endif
                                        </p>
                                    </div>

                                    @if ($entry['token'])
                                        <details class="ix-pts__redeem">
                                            <summary class="ix-btn ix-btn--primary">Redeem<span class="ix-sr"> {{ $reward->name }}</span></summary>
                                            <form method="POST" action="{{ route('points.redeem') }}" class="ix-pts__confirm" x-data="{ busy: false }" x-on:submit="busy = true">
                                                @csrf
                                                <input type="hidden" name="ticket" value="{{ $entry['token'] }}">
                                                <h5>Confirm this redemption</h5>
                                                <dl>
                                                    <div><dt>You spend</dt><dd>{{ number_format($reward->costPoints) }} points</dd></div>
                                                    @if ($balance !== null)
                                                        <div><dt>Balance after</dt><dd>{{ number_format($balance - $reward->costPoints) }} points</dd></div>
                                                    @endif
                                                    <div><dt>You get</dt><dd>{{ $reward->name }}. {{ \App\Services\Points\Reward::kindOutcome($reward->kind) }}</dd></div>
                                                </dl>
                                                <p>An operator fulfils it, so it may take time. If it fails or is cancelled, the points come back.</p>
                                                @if ($entry['unconfirmed'])
                                                    <p class="ix-pts__warn">An earlier request for this reward could not be confirmed. Check Your redemptions first. If you confirm again, nothing is spent twice.</p>
                                                @endif
                                                <button type="submit" class="ix-btn ix-btn--primary" x-bind:disabled="busy">Confirm: spend {{ number_format($reward->costPoints) }} points</button>
                                            </form>
                                        </details>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </section>

        <section class="ix-pts__section" aria-labelledby="pts-history">
            <h2 id="pts-history">Your redemptions</h2>
            @if ($historyDown)
                <p class="ix-pts__muted">Your redemptions are unavailable right now. A request you have made is not lost; it will show here when the points service is back.</p>
            @elseif ($history === [])
                <p class="ix-pts__muted">You have not redeemed anything yet.</p>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table ix-pts__table">
                        <caption class="ix-sr">Your redemptions, newest first</caption>
                        <thead>
                            <tr>
                                <th scope="col">Requested</th>
                                <th scope="col">Reward</th>
                                <th scope="col">Points</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $row)
                                <tr>
                                    <td data-label="Requested">{{ $row->requestedAt->format('j M Y') }}</td>
                                    <td data-label="Reward"><div>{{ $row->rewardName }}@if ($row->rewardKind)<small class="ix-pts__muted"> {{ \App\Services\Points\Reward::kindLabel($row->rewardKind) }}</small>@endif</div></td>
                                    <td data-label="Points">{{ number_format($row->pointsSpent) }}</td>
                                    <td data-label="Status"><div>
                                        <span class="ix-pts__status ix-pts__status--{{ $row->status }}">{{ \App\Services\Points\RedemptionRecord::statusLabel($row->status) }}</span>
                                        @if ($row->resolvedAt)<small class="ix-pts__muted"> {{ $row->resolvedAt->format('j M Y') }}</small>@endif
                                        @if ($row->message)<small class="ix-pts__note">{{ $row->message }}</small>@endif
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <section class="ix-pts__section ix-pts__pref" id="expiry-emails" aria-labelledby="pts-pref">
        <h2 id="pts-pref">Emails about lapsing points</h2>
        <form method="POST" action="{{ route('points.expiry-emails') }}">
            @csrf
            <input type="hidden" name="points_expiry_emails" value="0">
            <label class="ix-pts__check" for="pts-expiry-emails">
                <input type="checkbox" id="pts-expiry-emails" name="points_expiry_emails" value="1" aria-describedby="pts-pref-hint pts-pref-state" @checked($expiryEmails)>
                <span>Email me before my points expire</span>
            </label>
            <p class="ix-hint" id="pts-pref-hint">One plain email per batch of points about to lapse, with the number and the date. Nothing else.</p>
            @error('points_expiry_emails')
                <p class="ix-error" role="alert">Choose on or off, then save.</p>
            @enderror
            <p class="ix-pts__muted" id="pts-pref-state">Right now these emails are <strong>{{ $expiryEmails ? 'on' : 'off' }}</strong>.</p>
            <button type="submit" class="ix-btn">Save</button>
        </form>
    </section>

    <section class="ix-pts__section ix-pts__terms" aria-labelledby="pts-terms">
        <h2 id="pts-terms">How points work</h2>
        <ul>
            <li>Points have no cash value and cannot be transferred to anyone else.</li>
            <li>Each award lapses 12 months after it was granted. Redeeming spends the oldest points first.</li>
            <li>A redemption is fulfilled by an operator and may take time. It starts as pending.</li>
            <li>If a redemption fails or is cancelled, the points are returned to you.</li>
        </ul>
    </section>
</x-app-layout>
