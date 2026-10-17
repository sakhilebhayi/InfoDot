<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Team settings</h1>
            <p class="ix-lede">{{ $team->name }}</p>
        </div>
    </div>

    <div style="max-width: 980px;">
        @livewire('teams.update-team-name-form', ['team' => $team])

        @livewire('teams.team-member-manager', ['team' => $team])

        @if (Gate::check('delete', $team) && ! $team->personal_team)
            @livewire('teams.delete-team-form', ['team' => $team])
        @endif
    </div>
</x-app-layout>
