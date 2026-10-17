@props(['team', 'component' => 'jet-dropdown-link'])

<form method="POST" action="{{ route('current-team.update') }}">
    @method('PUT')
    @csrf

    <input type="hidden" name="team_id" value="{{ $team->id }}">

    <button type="submit" class="ix-pop__item" @if (Auth::user()->isCurrentTeam($team)) aria-current="true" @endif>
        <span class="material-symbols-rounded" aria-hidden="true">{{ Auth::user()->isCurrentTeam($team) ? 'check' : 'radio_button_unchecked' }}</span>
        <span class="truncate">{{ $team->name }}</span>
    </button>
</form>
