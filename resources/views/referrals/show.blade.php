<x-app-layout title="Refer a business">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Refer a business</h1>
            <p class="ix-lede">Share your link with another business. When it signs up, sets itself up and starts using a platform, it counts towards your rewards.</p>
        </div>
    </div>

    <section class="ix-panel" aria-label="Your link">
        <div class="ix-panel__body">
            <label for="ref-link" class="ix-label">Your link</label>
            <input id="ref-link" class="ix-input" type="text" readonly value="{{ $link }}" x-on:focus="$event.target.select()">
            <p class="ix-hint">Your code is <strong>{{ $code }}</strong>. It belongs to {{ $team->name }}. A link used by the same person, or by someone at your own company address, is not counted.</p>
        </div>
    </section>

    <section class="ix-panel ix-vgap" aria-label="Progress">
        <div class="ix-panel__head"><h2>Where your referrals stand</h2></div>
        <div class="ix-panel__body">
            <dl class="ix-biz__facts">
                <div><dt>Signed up</dt><dd><strong>{{ (int) ($states['registered'] ?? 0) + (int) ($states['verified'] ?? 0) + (int) ($states['activated'] ?? 0) }}</strong></dd></div>
                <div><dt>Active businesses</dt><dd><strong>{{ $activated }}</strong></dd></div>
            </dl>
        </div>
        <div class="ix-tablewrap">
            <table class="ix-table ix-adm__table">
                <thead><tr><th>Active businesses</th><th class="num">Points</th><th>Where it stands</th></tr></thead>
                <tbody>
                    @foreach ($tiers as $tier)
                        @php($reward = $rewards->firstWhere('tier_id', $tier->id))
                        <tr>
                            <td class="title">{{ $tier->referrals_required }}</td>
                            <td class="num">{{ number_format($tier->points) }}</td>
                            <td class="muted">
                                @if ($reward === null) {{ max(0, $tier->referrals_required - $activated) }} to go
                                @elseif ($reward->status === 'held') Earned. Held for {{ $holdingDays }} days before it is released.
                                @elseif ($reward->status === 'due') Earned and released.
                                @elseif ($reward->status === 'reversed') Taken back because a referral behind it no longer counts.
                                @else Sent. @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ix-panel__body">
            <p class="ix-hint">Rewards are for businesses that become active, never for inviting people to refer.</p>
        </div>
    </section>
</x-app-layout>
