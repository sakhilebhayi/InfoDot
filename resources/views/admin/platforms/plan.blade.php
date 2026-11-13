<x-app-layout :title="$plan->name">
    @include('admin.platforms._tabs', ['current' => 'catalogue'])

    @php $models = ['free' => 'Free', 'included' => 'Included', 'subscription' => 'Subscription', 'per_user' => 'Per person', 'usage' => 'By usage', 'enterprise' => 'Enterprise']; @endphp
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $plan->name }}</h1>
            <p class="ix-lede">A plan for {{ $entry?->name ?? $plan->platform_key }} <span class="ix-ref">{{ $plan->platform_key }}/{{ $plan->slug }}</span></p>
        </div>
        @if ($entry)<a href="{{ route('admin.platforms.platform', $entry) }}" class="ix-btn ix-btn--ghost">{{ $entry->name }}</a>@endif
    </div>

    @include('admin.platforms._flash')

    <div class="ix-adm__split">
        <section class="ix-panel" aria-labelledby="plan-h">
            <div class="ix-panel__head"><h2 id="plan-h">The plan</h2></div>
            <div class="ix-panel__body">
                <form method="post" action="{{ route('admin.platforms.plan.update', $plan) }}" class="ix-form">
                    @csrf
                    <div class="ix-form__row">
                        <div class="ix-field">
                            <label class="ix-label" for="name">Plan name</label>
                            <input id="name" name="name" type="text" maxlength="120" class="ix-input" value="{{ old('name', $plan->name) }}" required>
                        </div>
                        <div class="ix-field">
                            <label class="ix-label" for="model">Kind of plan</label>
                            <select id="model" name="model" class="ix-input ix-select" required>
                                @foreach ($models as $value => $text)<option value="{{ $value }}" @selected(old('model', $plan->model) === $value)>{{ $text }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    @include('admin.platforms._price-fields', ['of' => $plan, 'p' => 'plan'])
                    <div class="ix-field">
                        <label class="ix-label" for="trial">Trial length in days (optional)</label>
                        <input id="trial" name="trial_days" type="number" inputmode="numeric" min="1" max="{{ config('platform_subscriptions.max_trial_days') }}" class="ix-input ix-adm__short" value="{{ old('trial_days', $plan->trial_days) }}">
                    </div>
                    <fieldset class="ix-vchecks">
                        <label>
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" @checked(old('active', $plan->active))>
                            <span>Switched on. A plan is on sale only when it is switched on and its price is approved.</span>
                        </label>
                    </fieldset>
                    @if ($plan->approved_at)<p class="ix-hint">Changing the amount, the currency, the interval or the kind withdraws the approval, and the plan goes off sale until the new price is approved.</p>@endif
                    @include('admin.platforms._reason', ['id' => 'plan-reason'])
                    <div><button type="submit" class="ix-btn ix-btn--primary">Save the plan</button></div>
                </form>
            </div>
        </section>

        <div class="ix-adm__side">
            @include('admin.platforms._approval', [
                'of' => $plan, 'kind' => 'plan', 'onSale' => $plan->isSellable(), 'approval' => $events->firstWhere('type', 'price_approved'),
                'approve' => route('admin.platforms.plan.decide', [$plan, 'approve']), 'withdraw' => route('admin.platforms.plan.decide', [$plan, 'withdraw']),
            ])

            <section class="ix-panel" aria-labelledby="held-h">
                <div class="ix-panel__head"><h2 id="held-h">Companies on this plan</h2><span class="ix-hint">{{ $holders }}</span></div>
                <p class="ix-hint ix-panel__body">A change here never alters what a company already holds. Their entitlement, its dates and what they pay stay as they are; a new price applies only to what is agreed after it is approved.</p>
            </section>

            <section class="ix-panel" aria-labelledby="hist-h">
                <div class="ix-panel__head"><h2 id="hist-h">Changes</h2><span class="ix-hint">{{ $events->count() }}</span></div>
                <div class="ix-panel__body">@include('admin.platforms._trail', ['events' => $events])</div>
            </section>
        </div>
    </div>
</x-app-layout>
