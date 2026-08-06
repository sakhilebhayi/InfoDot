<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <div class="text-[var(--ink-soft)] text-center mb-5 text-sm">
            Sign in with your credentials
        </div>

        <x-jet-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-emerald-600">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input class="block mt-1 w-full" id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-jet-input class="block mt-1 w-full" id="password" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-jet-checkbox id="remember_me" name="remember" />
                    <span class="ml-2 text-sm text-[var(--ink-soft)]">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="mt-6">
                <x-jet-button class="w-full">
                    {{ __('Sign in') }}
                </x-jet-button>
            </div>

            @if (Route::has('password.request'))
                <div class="mt-4 text-center">
                    <a class="underline text-sm text-[var(--ink-soft)] hover:text-[var(--gold-deep)]" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                </div>
            @endif

            @if (Route::has('register'))
                <div class="mt-2 text-center">
                    <a class="underline text-sm text-[var(--ink-soft)] hover:text-[var(--ink)]" href="{{ route('register') }}">
                        {{ __("Don't have an account? Sign up") }}
                    </a>
                </div>
            @endif
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
