<x-guest-layout title="Reset your password" :panel="($platformBranding ?? null) ? null : 'account'">
    <x-jet-authentication-card>
        <x-auth.heading title="Reset your password" lede="It happens. Enter the email address of your account and we will send a link for choosing a new password. The link works for an hour." />

        @if (session('status'))
            <p class="ix-alert" role="status">{{ session('status') }}</p>
        @endif

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('password.email') }}" x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false">
            @csrf
            @if ($platformFlow)<input type="hidden" name="f" value="{{ $platformFlow }}">@endif

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <x-auth.submit label="Send the reset link" busy="Sending…" />
        </form>

        <p class="ix-entry__links">
            <a href="{{ route('login', $platformFlow ? ['f' => $platformFlow] : []) }}">Back to sign in</a>
        </p>
    </x-jet-authentication-card>
</x-guest-layout>
