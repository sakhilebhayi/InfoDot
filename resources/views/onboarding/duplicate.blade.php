<x-guest-layout title="Is this a new company?">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Is this a new company?</h1>

        @if ($found->isNotEmpty())
            <p class="ix-entry__lede">This looks like {{ $found->count() === 1 ? 'a company' : 'companies' }} you already manage:</p>
            <ul>
                @foreach ($found as $team)
                    <li><a href="{{ route('business.edit', $team) }}">{{ $team->name }}</a></li>
                @endforeach
            </ul>
        @endif

        @if ($elsewhere)
            <p class="ix-hint">Another company on InfoDot already uses the same registration number or website. Creating this company does not give you access to that one. If it is yours, ask its owner to invite you.</p>
        @endif

        <form class="ix-entry__form" method="POST" action="{{ route('onboarding.business.store') }}">
            @csrf
            <input type="hidden" name="confirm_duplicate" value="1">
            <input type="hidden" name="import" value="{{ $import }}">
            @if ($another ?? false)<input type="hidden" name="another" value="1">@endif
            @foreach ($fields as $key => $value)
                @if (is_array($value))
                    @foreach ($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach

            <x-jet-button class="ix-entry__go">It is a different company: create it</x-jet-button>
        </form>

        <p class="ix-entry__links"><a href="{{ route('dashboard') }}">Cancel</a></p>
    </x-jet-authentication-card>
</x-guest-layout>
