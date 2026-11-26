<x-guest-layout title="Create your account" :panel="$platformBranding ? null : 'register'">
    <x-jet-authentication-card>
        <x-auth.heading title="Create your free InfoDot account" kind="register" :brand="$platformBranding" lede="One account for the knowledge, tools and businesses on InfoDot. It takes a minute." />

        <x-jet-validation-errors />

        <form class="ix-entry__form" method="POST" action="{{ route('register') }}" x-data="{ sending: false }" x-on:submit="sending = true" x-on:pageshow.window="sending = false">
            @csrf
            @if ($platformFlow)<input type="hidden" name="f" value="{{ $platformFlow }}">@endif

            <div class="ix-field">
                <x-jet-label for="first_name" value="{{ __('First name') }}" />
                <x-jet-input id="first_name" type="text" name="first_name" :value="old('first_name')" required autofocus maxlength="120" autocomplete="given-name" />
            </div>

            <div class="ix-field">
                <x-jet-label for="last_name" value="{{ __('Last name') }}" />
                <x-jet-input id="last_name" type="text" name="last_name" :value="old('last_name')" required maxlength="120" autocomplete="family-name" />
            </div>
            <p class="ix-hint">You are creating your own login. You will set up your company next.</p>

            <div class="ix-field">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" aria-describedby="email-hint" />
                <p class="ix-hint" id="email-hint">We send a link to confirm it, because every Dot platform you open trusts this address.</p>
            </div>

            <div class="ix-field">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-password-input id="password" name="password" required autocomplete="new-password" />
                <x-auth.strength for="password" />
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

            <x-auth.submit :label="$platformBranding?->ctaRegister ?? 'Create account'" busy="Creating your account…" />
        </form>

        <p class="ix-hint">If a link brought you here, InfoDot keeps its referral code or campaign tag with your account to learn which links work. Nothing follows you to other sites. See <a href="{{ route('cookies') }}">Cookies</a>.</p>

        <p class="ix-entry__links">
            <a href="{{ route('login', $platformFlow ? ['f' => $platformFlow] : []) }}">Already have an account? Sign in</a>
        </p>
    </x-jet-authentication-card>
</x-guest-layout>
