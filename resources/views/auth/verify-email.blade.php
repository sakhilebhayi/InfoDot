<x-guest-layout title="Verify your email">
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <h1 class="ix-auth-title">Verify your email</h1>

        <div class="mb-4 text-sm text-[var(--ink-soft)]">
            {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>

        @if (session('verification_mail_failed'))
            <div class="mb-4 font-medium text-sm text-[color:var(--ix-danger)]" role="alert">
                Your account is ready, but we could not send the verification email just now. Press Resend in a minute.
            </div>
        @endif

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm text-emerald-600">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <div>
                    <x-jet-button type="submit">
                        {{ __('Resend Verification Email') }}
                    </x-jet-button>
                </div>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="underline text-sm text-[var(--ink-soft)] hover:text-[var(--ink)]">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </x-jet-authentication-card>
</x-guest-layout>
