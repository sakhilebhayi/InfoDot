<x-guest-layout title="Sign in">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Sign in</h1>
        <p class="ix-entry__lede">One account opens InfoDot and every live Dot platform.</p>

        <x-jet-validation-errors />

        @if (session('status'))
            <p class="ix-alert" role="status">{{ session('status') }}</p>
        @endif

        <form class="ix-entry__form" method="POST" action="{{ route('login') }}">
            @csrf

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-password-input id="password" name="password" required autocomplete="current-password" />
            </div>

            <label for="remember_me" class="ix-entry__check">
                <x-jet-checkbox id="remember_me" name="remember" />
                <span>{{ __('Remember me') }}</span>
            </label>

            <x-jet-button class="ix-entry__go">{{ __('Sign in') }}</x-jet-button>
        </form>

        <p class="ix-entry__links">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            @endif
            @if (Route::has('register'))
                <a href="{{ route('register') }}">New here? Create an account</a>
            @endif
        </p>
    </x-jet-authentication-card>
</x-guest-layout>
