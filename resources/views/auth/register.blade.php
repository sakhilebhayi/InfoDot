<x-guest-layout title="Create your account">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Create your account</h1>
        <p class="ix-entry__lede">Free. We email you a link to confirm the address before you start.</p>

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('register') }}" x-data="{ sending: false }" x-on:submit="sending = true">
            @csrf

            <div class="ix-field">
                <x-jet-label for="name" value="{{ __('Name') }}" />
                <x-jet-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-password-input id="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password_confirmation" value="{{ __('Confirm password') }}" />
                <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <label for="terms" class="ix-entry__check">
                    <x-jet-checkbox name="terms" id="terms" required />
                    <span>I agree to the <a target="_blank" rel="noopener" href="{{ route('terms') }}">Terms of Service</a> and the <a target="_blank" rel="noopener" href="{{ route('policy') }}">Privacy Policy</a></span>
                </label>
            @endif

            <x-jet-button class="ix-entry__go" x-bind:disabled="sending">Create account</x-jet-button>
        </form>

        <p class="ix-entry__links">
            <a href="{{ route('login') }}">Already have an account? Sign in</a>
        </p>
    </x-jet-authentication-card>
</x-guest-layout>
