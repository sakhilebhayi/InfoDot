<x-guest-layout title="Sign in">
    <x-jet-authentication-card>
        <x-auth.heading title="Sign in" kind="login" :brand="$platformBranding" :lede="$platformBranding ? 'Sign in with your InfoDot account. One account opens every Dot platform you are allowed to use.' : 'One account. Connected business tools. A community that shares what works.'" />

        <x-jet-validation-errors />

        @if (session('status'))
            <p class="ix-alert" role="status">{{ session('status') }}</p>
        @endif

        <form class="ix-entry__form" method="POST" action="{{ route('login') }}" x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false">
            @csrf
            @if ($platformFlow)<input type="hidden" name="f" value="{{ $platformFlow }}">@endif

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

            <x-auth.submit :label="$platformBranding?->ctaLogin ?? __('Sign in')" busy="Signing in…" />
        </form>

        <p class="ix-entry__links">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request', $platformFlow ? ['f' => $platformFlow] : []) }}">{{ __('Forgot your password?') }}</a>
            @endif
            @if (Route::has('register'))
                <a href="{{ route('register', $platformFlow ? ['f' => $platformFlow] : []) }}">New here? Create an account</a>
            @endif
            @if ($platformBranding)
                <a href="{{ route('login') }}">Not signing in to {{ $platformBranding->name }}? Use InfoDot on its own</a>
            @endif
        </p>
    </x-jet-authentication-card>
</x-guest-layout>
