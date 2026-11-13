<x-app-layout>
    @php
        $byRole = collect($people)->groupBy(fn ($p) => $p->company_role ?? 'Member')->map->count()->sortDesc();
    @endphp
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">People</h1>
            <p class="ix-lede">@if ($team && $team->personal_team)This is your personal workspace, so you are the only person in it. Create a company and you can invite the people you work with.@else Everyone working under <b>{{ $team->name ?? 'your workspace' }}</b>. Roles decide who can invite, change settings and see company-private knowledge.@endif</p>
        </div>
        @if ($team && ! $team->personal_team)
            <a href="{{ route('business.show', $team) }}" class="ix-btn ix-btn--ghost">
                <span class="material-symbols-rounded" aria-hidden="true">storefront</span>Business profile
            </a>
        @endif
        @if ($team && $team->personal_team)
            {{-- A personal workspace is one person's; people are invited into a business. --}}
            <a href="{{ route('teams.create') }}" class="ix-btn ix-btn--primary">
                <span class="material-symbols-rounded" aria-hidden="true">add</span>Create a company to invite people
            </a>
        @elseif ($canManage && $team)
            <a href="{{ route('teams.show', $team->id) }}" class="ix-btn ix-btn--primary">
                <span class="material-symbols-rounded" aria-hidden="true">person_add</span>Invite and manage
            </a>
        @endif
    </div>

    <div class="ix-crew">
        <aside class="ix-crew__count" aria-label="Headcount">
            <span class="ix-stencil-num">{{ str_pad((string) count($people), 2, '0', STR_PAD_LEFT) }}</span>
            <p>{{ \Illuminate\Support\Str::plural('person', count($people)) }} in the company</p>
            <ul>
                @foreach ($byRole as $role => $n)
                    <li><span>{{ $role }}</span><b>{{ $n }}</b></li>
                @endforeach
                @if ($invitations->isNotEmpty())
                    <li><span>Invited</span><b>{{ $invitations->count() }}</b></li>
                @endif
            </ul>
        </aside>

        <section aria-label="Roster">
            <ul class="ix-crew__list">
                @foreach ($people as $person)
                    @php($role = $person->company_role ?? 'Member')
                    <li class="ix-crewrow">
                        <span class="ix-crewrow__av" aria-hidden="true">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
                        <span class="ix-crewrow__who">
                            <a href="{{ route('profile.show', ['id' => $person->id]) }}">{{ $person->name }}</a>
                            @if ($person->id === auth()->id())<span class="ix-crewrow__you">You</span>@endif
                            <small>{{ $person->email }}</small>
                        </span>
                        <span class="ix-crewrow__role {{ $role === 'Owner' ? 'is-owner' : '' }}">{{ $role }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($invitations->isNotEmpty())
                <h2 class="ix-crew__sub">Invited, not yet joined</h2>
                <ul class="ix-crew__list">
                    @foreach ($invitations as $invitation)
                        <li class="ix-crewrow is-pending">
                            <span class="ix-crewrow__av" aria-hidden="true"><span class="material-symbols-rounded">mail</span></span>
                            <span class="ix-crewrow__who"><span>{{ $invitation->email }}</span>@if ($invitation->isExpired()) <span class="ix-chip ix-chip--open">Expired</span>@endif</span>
                            <span class="ix-crewrow__role">{{ \Laravel\Jetstream\Jetstream::findRole($invitation->role)?->name ?? $invitation->role }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-app-layout>
