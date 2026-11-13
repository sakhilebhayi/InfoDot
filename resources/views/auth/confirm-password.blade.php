<x-guest-layout title="Confirm your password">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Confirm your password</h1>
        <p class="ix-entry__lede">This part of your account is protected. Enter your password to continue.</p>

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-password-input id="password" name="password" required autocomplete="current-password" autofocus />
            </div>

            <x-jet-button class="ix-entry__go">{{ __('Confirm') }}</x-jet-button>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
