<x-guest-layout title="Stop the daily summary">
    <x-jet-authentication-card>
        @if ($done)
            <x-auth.heading title="You will not get the daily summary" lede="Nothing else changed: your notifications are still on your notifications page, and bookings, enquiries and account warnings reach you as they did. You can choose differently any time in your notification settings." />
            <p class="ix-entry__links"><a href="{{ route('notices.preferences') }}">Notification settings</a></p>
        @else
            <x-auth.heading title="Stop the daily summary?" lede="You will no longer get the once-a-day email that lists what is new on InfoDot. Account protection emails and anything you asked for as it happens are not affected." />
            <form method="POST" action="{{ url()->full() }}" class="ix-entry__form">
                @csrf
                <button type="submit" class="ix-btn ix-btn--primary">Stop the daily summary</button>
            </form>
        @endif
    </x-jet-authentication-card>
</x-guest-layout>
