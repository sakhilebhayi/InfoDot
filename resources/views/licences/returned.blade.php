@php
    $state = $batch->status === 'active' ? 'active' : ($batch->status === 'pending' ? 'waiting' : 'closed');
@endphp
@push('meta')
    @if ($refresh)<meta http-equiv="refresh" content="{{ $refresh }}">@endif
@endpush
<x-app-layout title="Your purchase">
    <div class="ix-plat ix-plat--confirm" data-licence-return="{{ $state }}">
        @if ($state === 'active')
            <h1 class="ix-h1">Your {{ $batch->quantity }} {{ $batch->quantity === 1 ? 'licence is' : 'licences are' }} ready</h1>
            <p class="ix-lede">The payment service confirmed your payment. {{ $team->name }} now has {{ $seats['available'] }} {{ $seats['available'] === 1 ? 'licence' : 'licences' }} free to give out.</p>
            <ul class="ix-plat__terms">
                <li>Renews {{ $batch->interval === 'year' ? 'every year' : 'every month' }}, next on {{ $batch->period_end?->timezone(config('app.timezone'))->format('j F Y') }}.</li>
                <li>Invite the people you want, or give a licence to someone already in {{ $team->name }}.</li>
            </ul>
            <div class="ix-actions"><a class="ix-btn ix-btn--primary" href="{{ route('teams.show', $team) }}#members">Invite someone</a><a class="ix-btn ix-btn--ghost" href="{{ route('teams.show', $team) }}#seats">See licences</a></div>
        @elseif ($state === 'waiting')
            <h1 class="ix-h1">{{ $result === 'failed' ? 'The payment was not completed' : 'Waiting for the payment to be confirmed' }}</h1>
            <p class="ix-lede" role="status">
                @if ($result === 'failed')
                    We have not received a confirmation, so no licences have been added and nothing has been given to anyone. If you were charged, you do not need to pay again: the licences appear by themselves once the payment service confirms.
                @elseif ($waitedOut)
                    This is taking longer than usual. Nothing has been lost: if the payment went through, the licences appear by themselves once it is confirmed, and you do not need to pay again. This page has stopped refreshing.
                @else
                    This page checks again every few seconds. Licences are added only when the payment service confirms your payment, never from this page.
                @endif
            </p>
            <ul class="ix-plat__terms">
                <li>{{ $batch->quantity }} {{ $batch->quantity === 1 ? 'licence' : 'licences' }}, paid {{ $batch->interval === 'year' ? 'yearly' : 'monthly' }}. Nobody has been given access yet.</li>
            </ul>
            <div class="ix-actions">
                <a class="ix-btn" href="{{ route('licences.return', ['batch' => $batch->uuid]) }}">Check now</a>
                @if ($isOwner)<a class="ix-btn ix-btn--primary" href="{{ route('licences.buy', ['team' => $team->uuid, 'quantity' => $batch->quantity, 'interval' => $batch->interval]) }}">Try the payment again</a>@endif
                <a class="ix-btn ix-btn--ghost" href="{{ route('teams.show', $team) }}#seats">Back to licences</a>
            </div>
        @else
            <h1 class="ix-h1">This purchase is no longer open</h1>
            <p class="ix-lede">{{ $team->name }} does not hold these licences. Nobody's access changed because of this purchase.</p>
            <div class="ix-actions"><a class="ix-btn ix-btn--primary" href="{{ route('teams.show', $team) }}#seats">Back to licences</a></div>
        @endif
    </div>
</x-app-layout>
