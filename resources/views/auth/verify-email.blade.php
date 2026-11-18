<x-guest-layout title="Verify your email">
    <x-jet-authentication-card>
        <x-auth.heading title="Verify your email" :lede="'We sent a link to '.(auth()->user()?->email ?? 'your email address').'. Open it to confirm the address and '.($platformBranding ? 'you will carry on to '.$platformBranding->name : 'you will arrive on your Home page').'. Other Dot platforms trust this address, which is why it is checked first. Check your spam folder if it does not arrive within a few minutes.'" />

        @if (session('verification_mail_failed'))
            <p class="ix-alert ix-alert--danger" role="alert">Your account is ready, but we could not send the verification email just now. Send the link again in a minute.</p>
        @endif

        @if (session('status') == 'verification-link-sent')
            <p class="ix-alert" role="status">A new link is on its way to the address you registered with.</p>
        @endif

        <div class="ix-entry__row">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-jet-button type="submit">Send the link again</x-jet-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ix-btn ix-btn--ghost">Sign out</button>
            </form>
        </div>
    </x-jet-authentication-card>
</x-guest-layout>
