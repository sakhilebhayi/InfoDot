@use('App\Services\Platforms\CompanyCatalogue')
@php
    $key = $row['key'];
    $name = $row['name'];
    $days = (int) $chosen->trial_days;
    $ends = now()->addDays($days);
@endphp
<x-app-layout :title="'Start a trial of '.$name">
    <div class="ix-plat ix-plat--confirm">
        <h1 class="ix-h1">Start a {{ $days }}-day trial of {{ $name }} for {{ $team->name }}?</h1>
        <p class="ix-lede">This is what happens when you confirm. Nothing happens until you press the button.</p>

        <ul class="ix-plat__terms" data-confirm-terms>
            <li>{{ $name }} opens straight away for everyone in {{ $team->name }} ({{ $headcount }} {{ $headcount === 1 ? 'person' : 'people' }} today). Afterwards you can limit it to selected people.</li>
            <li><strong>The trial ends on <time datetime="{{ $ends->toDateString() }}">{{ $ends->format('j F Y') }}</time>.</strong> That is {{ $days }} days from today.</li>
            <li><strong>Nothing is charged, now or when the trial ends.</strong> No payment details are asked for. When the trial ends, access simply ends, unless {{ $team->name }} chooses a paid plan later.</li>
            <li>The trial is of the {{ $chosen->name }} plan, which costs {{ CompanyCatalogue::priceOf($chosen) }} once a company pays for it. Paid plans cannot be started yet, so there is nothing to pay today.</li>
            <li>Nothing is deleted when the trial ends. What {{ $team->name }} made in {{ $name }} is kept there.</li>
            <li>{{ $team->name }} can have one trial of {{ $name }}. It cannot be started a second time.</li>
            <li>The owner and administrators of {{ $team->name }} are told in InfoDot that the trial started.</li>
        </ul>

        @if ($plans->count() > 1)
            <p class="ix-hint" data-trial-plans>
                Other plans with a trial:
                @foreach ($plans->where('id', '!=', $chosen->id) as $other)
                    <a href="{{ route('platforms.trial.confirm', ['platform' => $key, 'plan' => $other->slug]) }}">{{ $other->name }}, {{ $other->trial_days }} days</a>@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif

        <form method="POST" action="{{ route('platforms.trial', ['platform' => $key]) }}" class="ix-actions" data-platform-confirm="trial">
            @csrf
            <input type="hidden" name="company" value="{{ $team->uuid }}">
            <input type="hidden" name="plan" value="{{ $chosen->slug }}">
            <button type="submit" class="ix-btn ix-btn--primary">Start the trial of {{ $name }}</button>
            <a href="{{ route('platforms.show', ['platform' => $key]) }}" class="ix-btn ix-btn--ghost">Cancel</a>
        </form>
    </div>
</x-app-layout>
