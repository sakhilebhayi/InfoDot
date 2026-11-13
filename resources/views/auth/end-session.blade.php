<x-guest-layout title="Sign out">
    {{-- Shown when a sign-out request arrives without the signed-in person's own id_token: ending
         the session everywhere takes a button press that posts through the CSRF check. --}}
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Sign out of InfoDot and every Dot platform?</h1>
        <p class="ix-entry__lede">You are signed in as {{ $email }}. Signing out here also ends your session on each Dot platform you opened with this account.</p>

        <div class="ix-entry__row">
            <form method="POST" action="{{ route('openid.end_session_endpoint') }}">
                @csrf

                @foreach ($carry as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                <x-jet-button type="submit">Sign out</x-jet-button>
            </form>

            <a href="{{ url('/') }}" class="ix-btn ix-btn--ghost">Stay signed in</a>
        </div>
    </x-jet-authentication-card>
</x-guest-layout>
