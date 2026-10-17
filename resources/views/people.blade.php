<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">People</h1>
            <p class="ix-lede">Everyone working under <b>{{ $team->name ?? 'your workspace' }}</b>@if($team), owner code <span class="ix-ref">{{ $team->code }}</span>@endif.</p>
        </div>
        @if ($canManage && $team)
            <a href="{{ route('teams.show', $team->id) }}" class="ix-btn ix-btn--primary">
                <span class="material-symbols-rounded" aria-hidden="true">person_add</span>Invite and manage
            </a>
        @endif
    </div>

    <div class="ix-tablewrap">
        <table class="ix-table">
            <thead><tr><th>Person</th><th>Email</th><th>Role</th></tr></thead>
            <tbody>
            @foreach ($people as $person)
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:12px">
                            <span class="ix-avatar">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
                            <a class="title" href="{{ route('profile.show', ['id' => $person->id]) }}">{{ $person->name }}</a>
                            @if ($person->id === auth()->id())<span class="ix-chip ix-chip--info">You</span>@endif
                        </span>
                    </td>
                    <td class="muted">{{ $person->email }}</td>
                    <td><span class="ix-chip {{ ($person->company_role ?? '') === 'Owner' ? 'ix-chip--open' : '' }}">{{ $person->company_role ?? 'Member' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if ($invitations->isNotEmpty())
        <h2 class="ix-h1" style="font-size:16px;margin:28px 0 10px">Invited, not yet joined</h2>
        <div class="ix-tablewrap">
            <table class="ix-table">
                <thead><tr><th>Email</th><th>Role</th></tr></thead>
                <tbody>
                @foreach ($invitations as $invitation)
                    <tr><td>{{ $invitation->email }}</td><td class="muted">{{ \Laravel\Jetstream\Jetstream::findRole($invitation->role)?->name ?? $invitation->role }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
