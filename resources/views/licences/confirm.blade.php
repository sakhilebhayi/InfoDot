<x-app-layout :title="$title">
    <div class="ix-plat ix-plat--confirm" data-licence-confirm="{{ $action }}">
        <h1 class="ix-h1">{{ $title }}</h1>
        <p class="ix-lede">Read what changes before you confirm. Nothing happens until you press the button.</p>

        <ul class="ix-plat__terms" data-confirm-terms>
            @foreach ($terms as $line)<li>{{ $line }}</li>@endforeach
        </ul>

        <form method="POST" action="{{ route('licences.act', ['team' => $team->uuid, 'action' => $action]) }}" class="ix-actions">
            @csrf
            @isset($person)<input type="hidden" name="user" value="{{ $person->getKey() }}">@endisset
            @isset($to)<input type="hidden" name="to" value="{{ $to->getKey() }}">@endisset
            @isset($batch)<input type="hidden" name="batch" value="{{ $batch->uuid }}">@endisset
            @if ($action === 'reduce')<input type="hidden" name="quantity" value="{{ $quantity }}">@endif
            @unless ($unavailable ?? false)
                <button type="submit" class="ix-btn {{ $danger ? 'ix-btn--danger' : 'ix-btn--primary' }}">{{ $button }}</button>
            @endunless
            <a href="{{ route('teams.show', $team) }}#seats" class="ix-btn ix-btn--ghost">{{ ($unavailable ?? false) ? 'Back' : 'Cancel' }}</a>
        </form>
    </div>
</x-app-layout>
