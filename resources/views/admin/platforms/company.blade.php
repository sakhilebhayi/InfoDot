<x-app-layout :title="$team->name">
    @include('admin.platforms._tabs', ['current' => 'companies'])

    @php
        $sources = [
            'core' => 'Core', 'free' => 'Free plan', 'unpriced_free' => 'Free while pricing is finalised', 'included' => 'Included', 'complimentary' => 'Complimentary',
            'trial' => 'Trial', 'paid' => 'Paid', 'enterprise' => 'Enterprise',
        ];
        $said = [
            'activated' => 'Switched on', 'deactivated' => 'Switched off', 'complimentary_granted' => 'Complimentary access granted', 'suspended' => 'Suspended',
            'restored' => 'Restored', 'access_mode_changed' => 'Who may use it changed', 'user_granted' => 'A person was chosen', 'user_revoked' => 'A person was removed',
            'trial_started' => 'Trial started', 'trial_ended' => 'Trial ended', 'complimentary_ended' => 'Complimentary access ended', 'ended' => 'Ended',
            'grace_ended' => 'Grace period ended', 'period_ended' => 'Paid period ended', 'cancelled' => 'Cancelled', 'subscription_requested' => 'Paid plan requested',
            'payment_confirmed' => 'Payment confirmed by Dot.Finance', 'renewal_confirmed' => 'Renewal confirmed by Dot.Finance', 'payment_failed' => 'Payment failed', 'refunded' => 'Refunded',
        ];
        $name = fn (string $key) => $catalogue[$key]->name ?? $key;
        $ends = fn ($e) => match (true) {
            $e->status === 'trial' => $e->trial_ends_at,
            $e->status === 'payment_required' => $e->grace_until,
            $e->source === 'paid' => $e->current_period_ends_at,
            default => $e->ended_at,
        };
        $form = old('platform') !== null ? (string) old('_form') : '';
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $team->name }}</h1>
            <p class="ix-lede"><span class="ix-ref">{{ $team->code }}</span> <x-demo-mark :of="$team" /> Owned by {{ $team->owner?->name ?? 'nobody' }}. {{ $team->users_count + 1 }} {{ $team->users_count === 0 ? 'person' : 'people' }}.</p>
        </div>
        <a href="{{ route('admin.platforms.companies') }}" class="ix-btn ix-btn--ghost">All companies</a>
    </div>

    @include('admin.platforms._flash')

    <section class="ix-panel ix-panel--clip" aria-labelledby="held-h">
        <div class="ix-panel__head"><h2 id="held-h">Platforms</h2><span class="ix-hint">{{ $entitlements->count() }} beyond the core</span></div>
        <div class="ix-tablewrap">
            <table class="ix-table ix-adm__table">
                <thead><tr><th>Platform</th><th>Standing</th><th>Held as</th><th>For</th><th>Since</th><th>Until</th><th><span class="ix-sr">Manage</span></th></tr></thead>
                <tbody>
                    @foreach ($entitlements as $entitlement)
                        <tr @if ($selected?->id === $entitlement->id) aria-current="true" @endif>
                            <td class="title">{{ $name($entitlement->platform_key) }}</td>
                            <td>@include('admin.platforms._status', ['entitlement' => $entitlement])</td>
                            <td data-label="Held as">{{ $sources[$entitlement->source] ?? $entitlement->source }}@if ($entitlement->note)<span class="ix-vhist__note">{{ $entitlement->note }}</span>@endif</td>
                            <td data-label="For">{{ $entitlement->access_mode === 'selected' ? 'Selected people' : 'Everyone' }}</td>
                            <td class="muted" data-label="Since">{{ $entitlement->started_at?->format('j M Y') ?? 'Not started' }}</td>
                            <td class="muted" data-label="Until">{{ $ends($entitlement)?->format('j M Y') ?? 'No end date' }}</td>
                            <td><a class="ix-btn ix-btn--sm" href="{{ route('admin.platforms.company', ['team' => $team, 'platform' => $entitlement->platform_key]) }}#manage" @if ($selected?->id === $entitlement->id) aria-current="true" @endif>Manage<span class="ix-sr"> {{ $name($entitlement->platform_key) }}</span></a></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="title">{{ $core->pluck('name')->implode(', ') }}</td>
                        <td><span class="ix-chip ix-chip--done">Included</span></td>
                        <td colspan="5" class="ix-adm__soft">The free core. Every company has it, with no record and nothing to manage.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    @if ($selected)
        @php $sname = $name($selected->platform_key); @endphp
        <section class="ix-panel ix-vgap" id="manage" aria-labelledby="manage-h">
            <div class="ix-panel__head"><h2 id="manage-h">Manage {{ $sname }}</h2>@include('admin.platforms._status', ['entitlement' => $selected])</div>
            <div class="ix-panel__body ix-adm__acts">
                @if ($selected->status === 'suspended')
                    <form method="post" action="{{ route('admin.platforms.company.act', [$team, 'restore']) }}" class="ix-form">
                        @csrf
                        <input type="hidden" name="platform" value="{{ $selected->platform_key }}"><input type="hidden" name="_form" value="restore">
                        <h3 class="ix-adm__h">Restore</h3>
                        <p class="ix-hint">{{ $sname }} goes back to the standing it had when it was suspended.</p>
                        @include('admin.platforms._reason', ['id' => 'restore-reason', 'sticky' => $form === 'restore'])
                        <div><button type="submit" class="ix-btn ix-btn--primary">Restore {{ $sname }}</button></div>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.platforms.company.act', [$team, 'suspend']) }}" class="ix-form">
                        @csrf
                        <input type="hidden" name="platform" value="{{ $selected->platform_key }}"><input type="hidden" name="_form" value="suspend">
                        <h3 class="ix-adm__h">Suspend</h3>
                        <p class="ix-hint">Nobody in {{ $team->name }} can open {{ $sname }} until you restore it. Nothing is deleted, and the company cannot switch it back on itself.</p>
                        @include('admin.platforms._reason', ['id' => 'suspend-reason', 'sticky' => $form === 'suspend'])
                        <fieldset class="ix-vchecks"><label><input type="checkbox" name="confirm" value="1" required><span>I mean to suspend {{ $sname }} for this company.</span></label></fieldset>
                        <div><button type="submit" class="ix-btn ix-btn--danger">Suspend {{ $sname }}</button></div>
                    </form>
                @endif

                @unless ($selected->status === 'cancelled')
                    <form method="post" action="{{ route('admin.platforms.company.act', [$team, 'cancel']) }}" class="ix-form">
                        @csrf
                        <input type="hidden" name="platform" value="{{ $selected->platform_key }}"><input type="hidden" name="_form" value="cancel">
                        <h3 class="ix-adm__h">Cancel</h3>
                        <p class="ix-hint">Ends the company's use of {{ $sname }}. Time already paid for is honoured to the end of the period; anything else ends now. The platform keeps the company's data.</p>
                        @include('admin.platforms._reason', ['id' => 'cancel-reason', 'sticky' => $form === 'cancel'])
                        <fieldset class="ix-vchecks"><label><input type="checkbox" name="confirm" value="1" required><span>I mean to cancel {{ $sname }} for this company.</span></label></fieldset>
                        <div><button type="submit" class="ix-btn ix-btn--danger">Cancel {{ $sname }}</button></div>
                    </form>
                @endunless

                <form method="post" action="{{ route('admin.platforms.company.act', [$team, 'access']) }}" class="ix-form">
                    @csrf
                    <input type="hidden" name="platform" value="{{ $selected->platform_key }}"><input type="hidden" name="_form" value="access">
                    <h3 class="ix-adm__h">Who may use it</h3>
                    <fieldset class="ix-vchecks">
                        <label><input type="radio" name="mode" value="everyone" @checked($selected->access_mode === 'everyone')><span>Everyone in the company</span></label>
                        <label><input type="radio" name="mode" value="selected" @checked($selected->access_mode === 'selected')><span>Selected people. Owners and administrators always; the company chooses the rest.</span></label>
                    </fieldset>
                    @include('admin.platforms._reason', ['id' => 'access-reason', 'sticky' => $form === 'access'])
                    <div><button type="submit" class="ix-btn">Save who may use it</button></div>
                </form>
            </div>
        </section>
    @endif

    <section class="ix-panel ix-vgap" aria-labelledby="give-h">
        <div class="ix-panel__head"><h2 id="give-h">Give access</h2></div>
        <div class="ix-panel__body ix-adm__acts">
            <form method="post" action="{{ route('admin.platforms.company.act', [$team, 'grant']) }}" class="ix-form">
                @csrf
                <input type="hidden" name="_form" value="grant">
                <h3 class="ix-adm__h">Complimentary access</h3>
                <p class="ix-hint">The company gets the platform at no charge. It replaces whatever standing the platform has for them now.</p>
                <div class="ix-form__row">
                    <div class="ix-field">
                        <label class="ix-label" for="grant-platform">Platform</label>
                        <select id="grant-platform" name="platform" class="ix-input ix-select" required>
                            <option value="" disabled @selected($form !== 'grant')>Choose one</option>
                            @foreach ($grantable as $entry)<option value="{{ $entry->key }}" @selected($form === 'grant' && old('platform') === $entry->key)>{{ $entry->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="grant-until">Last day (optional)</label>
                        <input id="grant-until" name="until" type="date" class="ix-input" min="{{ now()->addDay()->toDateString() }}" value="{{ $form === 'grant' ? old('until') : '' }}">
                    </div>
                </div>
                @include('admin.platforms._reason', ['id' => 'grant-reason', 'sticky' => $form === 'grant'])
                <div><button type="submit" class="ix-btn ix-btn--primary">Grant complimentary access</button></div>
            </form>

            <form method="post" action="{{ route('admin.platforms.company.act', [$team, 'trial']) }}" class="ix-form">
                @csrf
                <input type="hidden" name="_form" value="trial">
                <h3 class="ix-adm__h">Trial</h3>
                <p class="ix-hint">A trial ends by itself. With a priced plan chosen it then waits for payment; without one it expires.</p>
                <div class="ix-form__row">
                    <div class="ix-field">
                        <label class="ix-label" for="trial-platform">Platform</label>
                        <select id="trial-platform" name="platform" class="ix-input ix-select" required>
                            <option value="" disabled @selected($form !== 'trial')>Choose one</option>
                            @foreach ($grantable as $entry)<option value="{{ $entry->key }}" @selected($form === 'trial' && old('platform') === $entry->key)>{{ $entry->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="trial-days">Length in days</label>
                        <input id="trial-days" name="days" type="number" inputmode="numeric" min="1" max="{{ config('platform_subscriptions.max_trial_days') }}" class="ix-input" value="{{ $form === 'trial' ? old('days') : '' }}">
                    </div>
                </div>
                @if ($trialPlans->isNotEmpty())
                    <div class="ix-field">
                        <label class="ix-label" for="trial-plan">Plan it leads to (optional; its own trial length is used when days is empty)</label>
                        <select id="trial-plan" name="plan" class="ix-input ix-select">
                            <option value="">No plan</option>
                            @foreach ($trialPlans as $plan)<option value="{{ $plan->id }}" @selected($form === 'trial' && (string) old('plan') === (string) $plan->id)>{{ $name($plan->platform_key) }}: {{ $plan->name }} ({{ $plan->trial_days }} days)</option>@endforeach
                        </select>
                    </div>
                @endif
                @include('admin.platforms._reason', ['id' => 'trial-reason', 'sticky' => $form === 'trial'])
                <div><button type="submit" class="ix-btn ix-btn--primary">Start the trial</button></div>
            </form>
        </div>
    </section>

    <section class="ix-panel ix-vgap" aria-labelledby="hist-h">
        <div class="ix-panel__head"><h2 id="hist-h">History</h2><span class="ix-hint">{{ $events->count() === 60 ? 'Latest 60' : $events->count() }}</span></div>
        <div class="ix-panel__body">
            @if ($events->isEmpty())
                <p class="ix-hint">Nothing has happened to this company's platforms yet.</p>
            @else
                <ol class="ix-vhist">
                    @foreach ($events as $event)
                        <li>
                            <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('j M Y, H:i') }}</time>
                            <span>
                                <strong>{{ $name($event->platform_key) }}</strong>: {{ $said[$event->type] ?? ucfirst(str_replace('_', ' ', $event->type)) }}
                                by {{ $event->actor?->name ?? (str_contains($event->type, 'confirmed') || in_array($event->type, ['payment_failed', 'refunded']) ? 'Dot.Finance' : 'the system') }}@if ($event->actor?->isPlatformAdmin()) (platform administrator)@endif.
                                @if ($event->from_status !== $event->to_status)<span class="ix-vhist__note">{{ $event->from_status ? 'From '.str_replace('_', ' ', $event->from_status).' to ' : 'Now ' }}{{ str_replace('_', ' ', (string) $event->to_status) }}</span>@endif
                                @if (! empty($event->details['until']))<span class="ix-vhist__note">Until {{ \Illuminate\Support\Carbon::parse($event->details['until'])->format('j M Y') }}</span>@endif
                                @if (! empty($event->details['note']))<span class="ix-vhist__note">Reason: {{ $event->details['note'] }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</x-app-layout>
