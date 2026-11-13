<x-guest-layout title="Sign out">
    {{-- Shown when a sign-out request arrives without the signed-in person's own id_token: ending
         the session everywhere takes a button press that posts through the CSRF check. --}}
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <h1 class="ix-auth-title">Sign out of InfoDot and every Dot platform?</h1>

        <div class="mb-4 text-sm text-[var(--ink-soft)]">
            You are signed in as {{ $email }}. Signing out here also ends your session on each Dot platform you opened with this account.
        </div>

        <div class="mt-4 flex items-center justify-between">
            <a href="{{ url('/') }}" class="underline text-sm text-[var(--ink-soft)] hover:text-[var(--ink)]">Stay signed in</a>

            <form method="POST" action="{{ route('openid.end_session_endpoint') }}">
                @csrf

                @foreach ($carry as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach

                <x-jet-button type="submit">Sign out</x-jet-button>
            </form>
        </div>
    </x-jet-authentication-card>
</x-guest-layout>
