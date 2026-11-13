<x-app-layout :title="$entry->name">
    @include('admin.platforms._tabs', ['current' => 'catalogue'])

    @php
        $statuses = [
            'operational' => 'Operational: people can use it', 'degraded' => 'Degraded: usable, with a known fault', 'maintenance' => 'Maintenance: closed for a short time',
            'unavailable' => 'Unavailable: down', 'development' => 'In development: not open to businesses', 'not_launched' => 'Not launched: built, not open to businesses',
        ];
        $models = ['free' => 'Free', 'included' => 'Included', 'subscription' => 'Subscription', 'per_user' => 'Per person', 'usage' => 'By usage', 'enterprise' => 'Enterprise'];
        $adding = old('model') !== null;
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $entry->name }}</h1>
            <p class="ix-lede"><span class="ix-ref">{{ $entry->key }}</span> {{ $entry->tagline }}</p>
        </div>
        <a href="{{ route('admin.platforms') }}" class="ix-btn ix-btn--ghost">All platforms</a>
    </div>

    @include('admin.platforms._flash')

    <section class="ix-panel ix-panel--clip" aria-labelledby="plans-h">
        <div class="ix-panel__head"><h2 id="plans-h">Plans and prices</h2><span class="ix-hint">{{ $plans->count() }}</span></div>
        @if ($entry->is_core || $ownSignIn)
            <p class="ix-hint ix-panel__body">{{ $entry->is_core ? 'A core platform is included for every company, so it has no plans.' : 'This platform has its own sign-in and is managed in the platform itself, so it has no plans here.' }}</p>
        @else
            @if ($plans->isEmpty())
                <p class="ix-hint ix-panel__body">No plan exists, so a company can switch {{ $entry->name }} on for free while pricing is being finalised. Nothing is charged until a plan is created here, its price is approved, and the company agrees to it.</p>
            @else
                <div class="ix-tablewrap">
                    <table class="ix-table ix-adm__table">
                        <thead><tr><th>Plan</th><th>Kind</th><th>Price</th><th>Trial</th><th>On sale</th></tr></thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr>
                                    <td><a class="title" href="{{ route('admin.platforms.plan', $plan) }}">{{ $plan->name }}</a> <span class="ix-ref">{{ $plan->slug }}</span></td>
                                    <td data-label="Kind">{{ $models[$plan->model] ?? $plan->model }}</td>
                                    <td data-label="Price">{{ \App\Support\Money::format($plan->amount_minor, $plan->currency) }}@if ($plan->amount_minor !== null && $plan->interval) a {{ $plan->interval }}@endif</td>
                                    <td data-label="Trial">{{ $plan->trial_days ? $plan->trial_days.' days' : 'None' }}</td>
                                    <td>
                                        @if ($plan->isSellable())<span class="ix-chip ix-chip--done">On sale</span>
                                        @elseif ($plan->approved_at)<span class="ix-chip">Approved, switched off</span>
                                        @else<span class="ix-chip ix-chip--open">Price not approved</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="ix-panel__body">
                <details @if ($adding) open @endif>
                    <summary class="ix-btn">Add a plan…</summary>
                    <form method="post" action="{{ route('admin.platforms.plans.store', $entry) }}" class="ix-form ix-vgap">
                        @csrf
                        <div class="ix-form__row">
                            <div class="ix-field">
                                <label class="ix-label" for="new-name">Plan name</label>
                                <input id="new-name" name="name" type="text" maxlength="120" class="ix-input" value="{{ $adding ? old('name') : '' }}" required>
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" for="new-model">Kind of plan</label>
                                <select id="new-model" name="model" class="ix-input ix-select" required>
                                    <option value="" disabled @selected(! $adding)>Choose one</option>
                                    @foreach ($models as $value => $text)<option value="{{ $value }}" @selected(old('model') === $value)>{{ $text }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        @include('admin.platforms._price-fields', ['of' => null, 'p' => 'new'])
                        <div class="ix-field">
                            <label class="ix-label" for="new-trial">Trial length in days (optional)</label>
                            <input id="new-trial" name="trial_days" type="number" inputmode="numeric" min="1" max="{{ config('platform_subscriptions.max_trial_days') }}" class="ix-input ix-adm__short" value="{{ $adding ? old('trial_days') : '' }}">
                        </div>
                        @include('admin.platforms._reason', ['id' => 'new-reason', 'sticky' => $adding])
                        <p class="ix-hint">A new plan is a draft. It goes on sale only after you approve its price on the next page and switch it on.</p>
                        <div><button type="submit" class="ix-btn ix-btn--primary">Create the plan</button></div>
                    </form>
                </details>
            </div>
        @endif
    </section>

    <div class="ix-adm__split ix-vgap">
        <section class="ix-panel" aria-labelledby="state-h">
            <div class="ix-panel__head"><h2 id="state-h">Status and description</h2></div>
            <div class="ix-panel__body">
                <form method="post" action="{{ route('admin.platforms.platform.update', $entry) }}" class="ix-form">
                    @csrf
                    <div class="ix-field">
                        <label class="ix-label" for="op">Working</label>
                        <select id="op" name="operational_status" class="ix-input ix-select">
                            @foreach ($statuses as $value => $text)<option value="{{ $value }}" @selected(old('operational_status', $entry->operational_status) === $value)>{{ $text }}</option>@endforeach
                        </select>
                        <p class="ix-hint">Businesses only ever see "temporarily unavailable" or "not launched". Nobody loses an entitlement when this changes.</p>
                    </div>
                    <fieldset class="ix-vchecks">
                        <label @if ($entry->is_core) aria-disabled="true" @endif>
                            <input type="hidden" name="discoverable" value="0">
                            <input type="checkbox" name="discoverable" value="1" @checked(old('discoverable', $entry->discoverable)) @disabled($entry->is_core)>
                            <span>Shown to businesses in the Platforms catalogue. @if ($entry->is_core) A core platform is always shown. @endif</span>
                        </label>
                    </fieldset>
                    <div class="ix-field">
                        <label class="ix-label" for="tagline">One line about it</label>
                        <input id="tagline" name="tagline" type="text" maxlength="160" class="ix-input" value="{{ old('tagline', $entry->tagline) }}">
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="description">What it does</label>
                        <textarea id="description" name="description" class="ix-textarea" rows="5" maxlength="2000">{{ old('description', $entry->description) }}</textarea>
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="benefit">What a business gains</label>
                        <textarea id="benefit" name="benefit" class="ix-textarea" rows="2" maxlength="1000">{{ old('benefit', $entry->benefit) }}</textarea>
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="audience">Who it is for</label>
                        <textarea id="audience" name="audience" class="ix-textarea" rows="2" maxlength="1000">{{ old('audience', $entry->audience) }}</textarea>
                    </div>
                    @include('admin.platforms._reason', ['id' => 'platform-reason', 'sticky' => ! $adding])
                    <div><button type="submit" class="ix-btn ix-btn--primary">Save changes</button></div>
                </form>
            </div>
        </section>

        <div class="ix-adm__side">
            <section class="ix-panel" aria-labelledby="facts-h">
                <div class="ix-panel__head"><h2 id="facts-h">Fixed facts</h2></div>
                <div class="ix-panel__body">
                    <dl class="ix-biz__facts">
                        <div><dt>Core</dt><dd>{{ $entry->is_core ? 'Yes: included for every company. The owner decides the core set.' : 'No: a company switches it on.' }}</dd></div>
                        <div><dt>Sign-in</dt><dd>{{ $ownSignIn ? 'Its own sign-in. InfoDot links to it and cannot enforce access.' : 'Through InfoDot.' }}</dd></div>
                        <div><dt>Registry key</dt><dd><span class="ix-ref">{{ $entry->key }}</span></dd></div>
                    </dl>
                </div>
            </section>

            <section class="ix-panel" aria-labelledby="hist-h">
                <div class="ix-panel__head"><h2 id="hist-h">Changes</h2><span class="ix-hint">{{ $events->count() }}</span></div>
                <div class="ix-panel__body">@include('admin.platforms._trail', ['events' => $events])</div>
            </section>
        </div>
    </div>
</x-app-layout>
