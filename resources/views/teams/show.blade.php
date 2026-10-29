<x-app-layout>
    @php
        $canDelete = Gate::check('delete', $team) && ! $team->personal_team;
        $sections = array_values(array_filter([
            ['name', 'Company name'],
            ['members', 'People and roles'],
            Gate::check('viewLicence', $team) && ! $team->personal_team ? ['seats', 'Seats'] : null,
            $canDelete ? ['delete', 'Delete company'] : null,
        ]));
    @endphp
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Team settings</h1>
            <p class="ix-lede">{{ $team->name }}@if($team->code) &middot; owner code <span class="ix-ref">{{ $team->code }}</span>@endif</p>
        </div>
    </div>

    <div class="ix-settings">
        <nav class="ix-settings__index" aria-label="Settings sections">
            @foreach ($sections as $i => [$id, $label])
                <a href="#{{ $id }}"><span class="ix-settings__no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ix-settings__body">
            <section id="name" class="ix-settings__sec">@livewire('teams.update-team-name-form', ['team' => $team])</section>
            <section id="members" class="ix-settings__sec">@livewire('teams.team-member-manager', ['team' => $team])</section>
            @if (Gate::check('viewLicence', $team) && ! $team->personal_team)
                <section id="seats" class="ix-settings__sec">@include('teams._seats', ['team' => $team, 'seats' => app(\App\Services\Licensing\SeatLedger::class)->summary($team), 'history' => app(\App\Services\Licensing\SeatLedger::class)->history($team)])</section>
            @endif
            @if ($canDelete)
                <section id="delete" class="ix-settings__sec">@livewire('teams.delete-team-form', ['team' => $team])</section>
            @endif
        </div>
    </div>
</x-app-layout>
