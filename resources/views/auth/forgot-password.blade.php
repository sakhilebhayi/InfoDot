<x-guest-layout title="Reset your password">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Reset your password</h1>
        <p class="ix-entry__lede">Enter the email address of your account and we will send a link for choosing a new password.</p>

        @if (session('status'))
            <p class="ix-alert" role="status">{{ session('status') }}</p>
        @endif

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <x-jet-button class="ix-entry__go">Send the reset link</x-jet-button>
        </form>

        <p class="ix-entry__links">
            <a href="{{ route('login') }}">Back to sign in</a>
        </p>
    </x-jet-authentication-card>
</x-guest-layout>
