<x-app-layout title="Accounts">
    @include('livewire.moderation._tabs', ['current' => 'accounts'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Accounts</h1>
            <p class="ix-lede">Find a person by name or email to read their suspension history, suspend the account or restore it. A suspended account cannot sign in to InfoDot or any Dot platform. {{ $suspended }} {{ $suspended === 1 ? 'account is' : 'accounts are' }} suspended now.</p>
        </div>
    </div>

    <form method="get" action="{{ route('moderation.accounts') }}" class="ix-dir__filters" role="search" aria-label="Find an account">
        {{-- On a phone the filter bar is a two-column grid; one field alone takes the whole row. --}}
        <label class="ix-dir__filter" style="grid-column: 1 / -1"><span>Name or email</span><input class="ix-input" type="search" name="q" maxlength="120" value="{{ $q }}" autocomplete="off"></label>
        <button type="submit" class="ix-btn">Find</button>
        @if ($q !== '')<a class="ix-btn ix-btn--ghost" href="{{ route('moderation.accounts') }}">Clear</a>@endif
    </form>
    @if ($errors->any())<div class="ix-error ix-vgap" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @if ($q !== '' && ! $searching)<p class="ix-hint ix-vgap">Type at least two characters to search. Showing the newest sign-ups meanwhile.</p>@endif

    <section class="ix-panel ix-panel--clip ix-vgap" aria-label="{{ $searching ? 'Accounts found' : 'Newest sign-ups' }}">
        <div class="ix-panel__head">
            <h2>{{ $searching ? 'Matching “'.$q.'”' : 'Newest sign-ups' }}</h2>
            <span class="ix-hint">{{ $searching ? $people->total() : $people->count() }}</span>
        </div>
        @if ($people->isEmpty())
            <p class="ix-hint ix-panel__body">{{ $searching ? 'No account has that in its name or email address. Check the spelling, or try part of the address.' : 'Nobody has signed up yet.' }}</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Email address</th><th>Companies</th><th>Standing</th></tr></thead>
                    <tbody>
                        @foreach ($people as $person)
                            @php
                                $companies = $person->ownedTeams->merge($person->teams)->where('personal_team', false)->unique('id');
                            @endphp
                            <tr>
                                <td><a class="title" href="{{ route('moderation.accounts.show', $person) }}">{{ $person->name }}</a>@if ($person->isModerator()) <span class="ix-chip ix-chip--info">Moderator</span>@endif <x-demo-mark :of="$person" /></td>
                                <td>{{ $person->email }}</td>
                                <td class="muted">@if ($person->created_at)<time datetime="{{ $person->created_at->toIso8601String() }}">{{ $person->created_at->format('j M Y') }}</time>@else — @endif</td>
                                <td>@if ($person->email_verified_at)<span class="ix-chip ix-chip--done">Email verified</span>@else<span class="ix-chip">Email not verified</span>@endif</td>
                                <td class="muted">{{ $companies->isEmpty() ? 'No company' : $companies->pluck('name')->implode(', ') }}</td>
                                <td>
                                    @if ($person->isSuspended())
                                        <span class="ix-chip ix-chip--open">Suspended since {{ $person->suspended_at->format('j M Y') }}</span>
                                        @if ($person->suspended_reason)<span class="ix-vhist__note">{{ $person->suspended_reason }}</span>@endif
                                    @else
                                        <span class="ix-chip ix-chip--done">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($searching)
        @include('partials.pager', ['pager' => $people, 'label' => 'Accounts'])
    @else
        <p class="ix-hint ix-vgap">Only the {{ $people->count() }} newest accounts are listed here. Everyone else is found by searching.</p>
    @endif
</x-app-layout>
