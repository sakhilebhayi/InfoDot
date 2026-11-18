<x-guest-layout title="Confirm your password">
    <x-jet-authentication-card>
        <x-auth.heading title="Confirm your password" lede="This part of your account is protected. Enter your password to continue." />

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('password.confirm') }}" x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false">
            @csrf

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-password-input id="password" name="password" required autocomplete="current-password" autofocus />
            </div>

            <x-auth.submit :label="__('Confirm')" busy="Checking…" />
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
