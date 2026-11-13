@php
    $key = $row['key'];
    $name = $row['name'];
    $unpriced = $row['state'] === 'free_while_pricing_finalised' || ($off && ($row['entitlement']?->source === 'unpriced_free'));
@endphp
<x-app-layout :title="($off ? 'Switch off ' : 'Switch on ').$name">
    <div class="ix-plat ix-plat--confirm">
        <h1 class="ix-h1">{{ $off ? 'Switch off' : 'Switch on' }} {{ $name }} for {{ $team->name }}?</h1>
        <p class="ix-lede">{{ $off ? 'Read what changes before you confirm. Nothing happens until you press the button.' : 'This is what happens when you confirm. Nothing happens until you press the button.' }}</p>

        <ul class="ix-plat__terms" data-confirm-terms>
            @if ($off)
                <li>People in {{ $team->name }} can no longer open {{ $name }} from InfoDot.</li>
                <li>Nothing is deleted. InfoDot keeps the record that the company used {{ $name }}, and never tells {{ $name }} to delete anything.</li>
                <li>You can switch it on again at any time while it stays free.</li>
                <li>Administrators of {{ $team->name }} are told in InfoDot that it was switched off.</li>
            @else
                <li>{{ $name }} opens straight away for everyone in {{ $team->name }} ({{ $headcount }} {{ $headcount === 1 ? 'person' : 'people' }} today). Afterwards you can limit it to selected people.</li>
                @if ($unpriced)
                    <li><strong>It is free for now.</strong> No price has been set for {{ $name }}, so nothing is charged.</li>
                    <li><strong>No charge will ever be made without new, explicit consent.</strong> If a price is set later, the owner of {{ $team->name }} is asked to agree to it in a separate step before anything is charged.</li>
                @else
                    <li><strong>It is free.</strong> Nothing is charged, and no charge will ever be made without new, explicit consent from the owner of {{ $team->name }}.</li>
                @endif
                <li>
                    @if ($owns)
                        You can switch it off again whenever you like. Nothing is deleted when you do.
                    @else
                        Only the owner{{ $ownerName !== '' ? ', '.$ownerName.',' : '' }} can switch it off again. Nothing is deleted when they do.
                    @endif
                </li>
                <li>The owner and administrators of {{ $team->name }} are told in InfoDot that it was switched on.</li>
            @endif
        </ul>

        <form method="POST" action="{{ route($off ? 'platforms.switch-off' : 'platforms.switch-on', ['platform' => $key]) }}" class="ix-actions" data-platform-confirm="{{ $off ? 'off' : 'on' }}">
            @csrf
            <input type="hidden" name="company" value="{{ $team->uuid }}">
            <button type="submit" class="ix-btn {{ $off ? 'ix-btn--danger' : 'ix-btn--primary' }}">{{ $off ? 'Switch off' : 'Switch on' }} {{ $name }}</button>
            <a href="{{ route('platforms.show', ['platform' => $key]) }}" class="ix-btn ix-btn--ghost">Cancel</a>
        </form>
    </div>
</x-app-layout>
