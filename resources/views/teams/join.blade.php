<x-guest-layout title="Join a company">
    <x-jet-authentication-card>
        @if ($problem)
            <h1 class="ix-auth-title">This link does not work</h1>
            <p class="ix-entry__lede">
                @switch($problem)
                    @case('expired') This link has run out. Ask the person who shared it for a new one. @break
                    @case('revoked') This link was turned off. Ask the person who shared it for a new one. @break
                    @case('used_up') This link has been used by as many people as it allowed. Ask the person who shared it for a new one. @break
                    @default We could not find this link. Check that you copied all of it, or ask for a new one.
                @endswitch
            </p>
            <p class="ix-entry__links"><a href="{{ route('dashboard') }}">Go to InfoDot</a></p>
        @else
            <h1 class="ix-auth-title">Join {{ $company }}</h1>
            <p class="ix-entry__lede">You have been invited to join {{ $company }} on InfoDot as a member.</p>

            @error('link')<p class="ix-hint" role="alert">{{ $message }}</p>@enderror
            @error('email')<p class="ix-hint" role="alert">{{ $message }}</p>@enderror

            @auth
                <form class="ix-entry__form" method="POST" action="{{ route('join.accept', $token) }}">
                    @csrf
                    <x-jet-button class="ix-entry__go">Join {{ $company }}</x-jet-button>
                </form>
                <p class="ix-hint">You are signed in as {{ auth()->user()->email }}.</p>
            @else
                <p class="ix-entry__links">
                    <a class="ix-btn ix-btn--primary" href="{{ route('login') }}">Sign in to join</a>
                    <a class="ix-btn" href="{{ route('register') }}">Create an account</a>
                </p>
                <p class="ix-hint">After you sign in you come back here and choose to join. Nothing happens until you do.</p>
            @endauth
        @endif
    </x-jet-authentication-card>
</x-guest-layout>
