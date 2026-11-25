<x-app-layout title="Providers">
    @include('business.booking._nav', ['team' => $team, 'active' => 'providers'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Providers</h1>
            <p class="ix-lede">The people in {{ $team->name }} who take appointments. Customers see only the name you give here, never the person's account or email.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    @if ($providers->isEmpty())
        <section class="ix-panel" aria-label="Providers">
            <div class="ix-panel__body">
                <p>No providers yet. Add the first one, or leave this empty and {{ $team->name }} takes appointments itself, on the business hours.</p>
            </div>
        </section>
    @else
        <section class="ix-panel ix-panel--clip" aria-label="Providers">
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>Name customers see</th><th>Account (only your team sees this)</th><th>Hours</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($providers as $provider)
                            <tr>
                                <td class="title" data-label="Name customers see">
                                    <form method="post" action="{{ route('business.booking.providers.update', [$team, $provider]) }}" class="ix-actions">@csrf
                                        <input class="ix-input" aria-label="Name customers see for {{ $provider->user?->name }}" name="public_name" type="text" required maxlength="80" value="{{ $provider->public_name }}">
                                        <button type="submit" class="ix-btn ix-btn--sm ix-btn--ghost">Save name</button>
                                    </form>
                                </td>
                                <td class="muted" data-label="Account">Account: {{ $provider->user?->name }}</td>
                                <td class="muted" data-label="Hours">{{ $provider->working_hours_count > 0 ? 'Has own hours' : 'Works the business hours' }}</td>
                                <td data-label="Status"><span class="ix-chip @if($provider->active) ix-chip--done @else ix-chip--open @endif">{{ $provider->active ? 'On' : 'Off' }}</span></td>
                                <td>
                                    <div class="ix-actions">
                                        <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('business.booking.providers.hours', [$team, $provider]) }}">Hours</a>
                                        <form method="post" action="{{ route('business.booking.providers.toggle', [$team, $provider]) }}">@csrf
                                            <button type="submit" class="ix-btn ix-btn--sm ix-btn--ghost">{{ $provider->active ? 'Switch off' : 'Switch on' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('business.booking.providers.delete', [$team, $provider]) }}" x-data x-on:submit="if (! confirm('Remove this provider? This cannot be undone.')) $event.preventDefault()">@csrf
                                            <button type="submit" class="ix-btn ix-btn--sm ix-btn--danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <p class="ix-hint">A provider who already has appointments cannot be removed; switch them off and they stop taking new ones.</p>
    @endif

    <section class="ix-panel" aria-labelledby="add-h">
        <div class="ix-panel__body">
            <h2 id="add-h" class="ix-h2">Add a provider</h2>
            @if ($candidates->isEmpty())
                <p class="ix-hint">Everyone in {{ $team->name }} already takes appointments. <a href="{{ route('people') }}">Invite more people</a> first.</p>
            @else
                <p class="ix-hint">Customers see the name below. It starts as the person's first name; change it if you prefer.</p>
                <form method="post" action="{{ route('business.booking.providers.store', $team) }}" class="ix-form">
                    @csrf
                    <div class="ix-form__row">
                        <div>
                            <label class="ix-label" for="user_id">Person</label>
                            <select class="ix-input" id="user_id" name="user_id" required>
                                @foreach ($candidates as $person)
                                    <option value="{{ $person->id }}" @selected((int) old('user_id') === $person->id)>{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ix-label" for="public_name">Name customers see <span class="ix-hint">leave blank to use their first name</span></label>
                            <input class="ix-input" id="public_name" name="public_name" type="text" maxlength="80" value="{{ old('public_name') }}">
                        </div>
                    </div>
                    <div><button type="submit" class="ix-btn ix-btn--primary">Add the provider</button></div>
                </form>
            @endif
        </div>
    </section>
</x-app-layout>
