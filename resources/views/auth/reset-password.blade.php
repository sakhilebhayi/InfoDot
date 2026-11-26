<x-guest-layout title="Choose a new password" :panel="($platformBranding ?? null) ? null : 'account'">
    <x-jet-authentication-card>
        <x-auth.heading title="Choose a new password" lede="Pick one you do not use anywhere else. Once it is saved, sign in with it and carry on where you left off." />

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('password.update') }}" x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('New password') }}" />
                <x-password-input id="password" name="password" required autocomplete="new-password" />
                <x-auth.strength for="password" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password_confirmation" value="{{ __('Confirm new password') }}" />
                <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <x-auth.submit label="Save the new password" busy="Saving…" />
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
