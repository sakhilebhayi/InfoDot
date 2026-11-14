@use('App\Services\Platforms\CompanyCatalogue')
@use('App\Models\PlatformEntitlement')
@use('App\Support\PlatformCategories')
@php
    $key = $row['key'];
    $name = $row['name'];
    $entitlement = $row['entitlement'];
    $company = $isCompany ? $team->name : 'your company';
    $chip = match (true) {
        in_array($row['state'], ['included', 'active', 'trial'], true) => 'ix-chip--done',
        in_array($row['state'], [...CompanyCatalogue::SWITCHABLE, 'trial_available'], true) => 'ix-chip--info',
        in_array($row['state'], ['payment_required', 'trial_ended', 'subscription_expired', 'suspended', 'activation_pending'], true) => 'ix-chip--open',
        default => '',
    };
    $mode = $entitlement?->access_mode ?? PlatformEntitlement::EVERYONE;
    $unpriced = $plans->isEmpty();
@endphp
<x-app-layout :title="$name">
    <div class="ix-plat">
        @if (session('status'))
            <p class="ix-alert" role="status">{{ session('status') }}</p>
        @endif

        <header class="ix-plat__head">
            <div class="ix-plat__id">
                <span class="ix-regrow__logo ix-plat__logo">
                    @if (file_exists(resource_path('img/apps/'.$key.'.png')))
                        <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/apps/'.$key.'.png') }}" alt="{{ $name }} logo" width="160" height="108">
                    @else
                        <span class="material-symbols-rounded" aria-hidden="true">{{ config('ecosystem.platforms.'.$key.'.icon', 'apps') }}</span>
                    @endif
                </span>
                <div>
                    <h1 class="ix-h1">{{ $name }}</h1>
                    <p class="ix-lede">{{ $row['tagline'] }}</p>
                </div>
            </div>

            <div class="ix-plat__state" data-state="{{ $row['state'] }}">
                <p class="ix-plat__for">{{ $isCompany ? 'For '.$team->name : 'In your personal workspace' }}</p>
                <p><span class="ix-chip {{ $chip }}">{{ $row['label'] }}</span></p>
                <p class="ix-plat__why">{{ $row['why'] }}</p>
                <div class="ix-actions">
                    @if ($row['action'] === 'open')
                        <a class="ix-btn ix-btn--primary" data-platform-action="open" href="{{ route('platform.launch', ['platform' => $key]) }}">Open {{ $name }}</a>
                    @elseif ($row['action'] === 'switch_on')
                        <a class="ix-btn ix-btn--primary" data-platform-action="switch_on" href="{{ route('platforms.switch-on.confirm', ['platform' => $key]) }}">Switch on {{ $name }}</a>
                    @elseif ($row['action'] === 'start_trial')
                        <a class="ix-btn ix-btn--primary" data-platform-action="start_trial" href="{{ route('platforms.trial.confirm', ['platform' => $key]) }}">Start the {{ $row['trial_days'] }}-day trial</a>
                    @elseif ($row['action'] === 'finance' && $financeUrl)
                        <a class="ix-btn ix-btn--primary" data-platform-action="finance" href="{{ $financeUrl }}">Open Dot.Finance</a>
                    @elseif ($row['state'] === 'company_required')
                        <a class="ix-btn ix-btn--primary" data-platform-action="create_company" href="{{ route('teams.create') }}">Create a company</a>
                    @elseif ($row['state'] === 'suspended' && Route::has('contact'))
                        <a class="ix-btn ix-btn--primary" data-platform-action="support" href="{{ route('contact') }}">Contact support</a>
                    @endif
                    <a class="ix-btn {{ $row['action'] === 'details' && ! in_array($row['state'], ['company_required', 'suspended'], true) ? '' : 'ix-btn--ghost' }}" href="{{ route('ecosystem.widget') }}">All platforms</a>
                </div>
            </div>
        </header>

        <div class="ix-prose ix-prose--ledger ix-plat__body">
            <h2>What it is for</h2>
            <div>
                <p>{{ $entry->description }}</p>
                @if ($entry->benefit)<p>{{ $entry->benefit }}</p>@endif
            </div>

            @if ($entry->audience)
                <h2>Who it suits</h2>
                <p>{{ $entry->audience }}</p>
            @endif

            @if (count((array) $entry->capabilities))
                <h2>What it does</h2>
                <ul>
                    @foreach ((array) $entry->capabilities as $capability)
                        <li>{{ $capability }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($connections->isNotEmpty())
                <h2>Works with</h2>
                <p class="ix-plat__links">
                    @foreach ($connections as $other)
                        <a href="{{ route('platforms.show', ['platform' => $other['key']]) }}">{{ $other['name'] }}</a>@if (! $loop->last), @endif
                    @endforeach
                    and InfoDot, where you sign in.
                </p>
            @endif

            <h2>What it costs</h2>
            <div data-pricing="{{ $unpriced ? 'none' : 'approved' }}">
                @if ($entry->is_core)
                    <p>{{ $name }} is part of the free core that every company has. There is nothing to pay and nothing to switch on.</p>
                @elseif ($row['state'] === 'managed_in_platform')
                    <p>{{ $name }} has its own sign-in and its own accounts. Anything it charges is arranged in {{ $name }} itself, not in InfoDot.</p>
                @elseif ($unpriced)
                    <p>Free while pricing is being finalised. No price has been set for {{ $name }}.</p>
                    <p>Nothing is charged now. If a price is set later, nothing will be charged unless the owner of the company agrees to it first, in a new and separate step.</p>
                @else
                    <ul class="ix-plat__plans">
                        @foreach ($plans as $plan)
                            <li><strong>{{ $plan->name }}</strong> <span>{{ CompanyCatalogue::priceOf($plan) }}@if ($trialPlans->contains('id', $plan->id)), after a trial of {{ $plan->trial_days }} days if you start one @endif</span></li>
                        @endforeach
                    </ul>
                    @if ($row['state'] === 'trial_available')
                        <p data-trial-offer><strong>A trial costs nothing.</strong> {{ $manages ? 'You can start one for '.$team->name.'.' : 'The owner or an administrator of '.$company.' can start one.' }} When it ends, access simply ends: nothing is charged, and nothing is deleted. A company can have one trial of {{ $name }}.</p>
                    @endif
                    @if (! $row['held'] && ! $plans->contains->isFree())
                        <p data-paid-not-open><strong>Paid activation is not open yet.</strong> Payment for a platform runs through Dot.Finance, which is not taking payments yet, so this plan cannot be started and nothing can be charged. The price above is the approved one; it is shown so you know it, not so you can buy it today.</p>
                    @endif
                @endif
            </div>

            <h2 id="people">Who may open it</h2>
            <div class="ix-plat__people">
                @if (! $isCompany)
                    <p>{{ $entry->is_core ? 'You. A personal workspace belongs to one person.' : 'Nobody from a personal workspace. '.$name.' is for companies.' }}</p>
                @elseif ($entry->is_core)
                    <p>Everyone in {{ $team->name }}. The free core is always open to the whole company.</p>
                @elseif ($row['state'] === 'managed_in_platform')
                    <p>Whoever has an account in {{ $name }}. InfoDot does not decide who signs in there.</p>
                @elseif ($entitlement === null || ! $row['held'])
                    <p>Nobody yet. Once {{ $name }} is switched on, it opens for everyone in {{ $team->name }}, and the owner or an administrator can then limit it to selected people.</p>
                @elseif ($manages)
                    <form method="POST" action="{{ route('platforms.people', ['platform' => $key]) }}" class="ix-form" x-data="{ mode: @js(old('access_mode', $mode)) }">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="company" value="{{ $team->uuid }}">
                        <fieldset class="ix-choice">
                            <legend class="ix-sr">Who in {{ $team->name }} may open {{ $name }}</legend>
                            <label class="ix-choice__opt">
                                <input type="radio" name="access_mode" value="everyone" x-model="mode" @checked($mode === 'everyone')>
                                <span><strong>Everyone in {{ $team->name }}</strong><span class="ix-hint">Anyone who joins the company can open it too.</span></span>
                            </label>
                            <label class="ix-choice__opt">
                                <input type="radio" name="access_mode" value="selected" x-model="mode" @checked($mode === 'selected')>
                                <span><strong>Selected people</strong><span class="ix-hint">Only the people ticked below. The owner and administrators can always open it.</span></span>
                            </label>
                        </fieldset>
                        <fieldset class="ix-plat__roster" x-show="mode === 'selected'">
                            <legend>People in {{ $team->name }}</legend>
                            <ul>
                                @foreach ($people as $person)
                                    <li>
                                        <label class="ix-choice__chk">
                                            @if ($person['always'])
                                                <input type="checkbox" checked disabled>
                                            @else
                                                <input type="checkbox" name="people[]" value="{{ $person['user']->id }}" @checked(in_array($person['user']->id, $selected, true))>
                                            @endif
                                            <span>{{ $person['user']->name }}</span>
                                            @if ($person['always'])<small>{{ $person['role'] }}, always</small>@endif
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </fieldset>
                        <div class="ix-actions"><button type="submit" class="ix-btn">Save who may open it</button></div>
                    </form>
                @elseif ($mode === 'selected')
                    <p>Selected people in {{ $team->name }}, plus the owner and administrators. {{ $youAreSelected ? 'You are one of them.' : 'You are not one of them yet.' }}</p>
                @else
                    <p>Everyone in {{ $team->name }}.</p>
                @endif
            </div>

            @if ($manages && ! $entry->is_core && $row['state'] !== 'managed_in_platform')
                <h2>History</h2>
                <div data-platform-history>
                    @if ($history->isEmpty())
                        <p>Nothing has happened yet. {{ $team->name }} has never switched {{ $name }} on.</p>
                    @else
                        <div class="ix-tablewrap">
                            <table class="ix-table">
                                <thead><tr><th scope="col">When</th><th scope="col">What happened</th><th scope="col">By</th></tr></thead>
                                <tbody>
                                    @foreach ($history as $event)
                                        <tr>
                                            <td class="muted"><time datetime="{{ $event['at']?->toIso8601String() }}">{{ $event['at']?->timezone(config('app.timezone'))->format('j M Y, H:i') }}</time></td>
                                            <td>{{ $event['text'] }}</td>
                                            <td>{{ $event['by'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="ix-hint">Only the owner and administrators of {{ $team->name }} see this history.</p>
                    @endif
                </div>
            @endif

            @if ($canSwitchOff)
                <h2>Switch it off</h2>
                <div>
                    <p>People in {{ $team->name }} would no longer be able to open {{ $name }}. Nothing is deleted, and it can be switched on again.</p>
                    <a class="ix-btn ix-btn--danger" href="{{ route('platforms.switch-off.confirm', ['platform' => $key]) }}">Switch off {{ $name }}</a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
