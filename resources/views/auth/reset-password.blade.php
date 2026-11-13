<x-guest-layout title="Choose a new password">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Choose a new password</h1>

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('New password') }}" />
                <x-password-input id="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password_confirmation" value="{{ __('Confirm new password') }}" />
                <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <x-jet-button class="ix-entry__go">Save the new password</x-jet-button>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
