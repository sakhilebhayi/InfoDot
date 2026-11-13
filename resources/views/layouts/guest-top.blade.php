{{-- The top bar for visitors who are not signed in, on pages drawn by the app layout (the directory, a business
     page). It carries the same three places and the same two ways in, in the same words and sizes, as the bar on
     the public pages (components/public-layout), so a visitor never meets two different InfoDots. --}}
<header class="ix-top ix-top--public">
    <div class="ix-top__inner">
        <a href="{{ url('/') }}" class="ix-brand" aria-label="InfoDot home">
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/brand/logo_white.png') }}" alt="InfoDot" width="64" height="26">
        </a>
        <div class="ix-top__actions">
            <a href="{{ route('login') }}" class="ix-btn">Sign in</a>
            @if (Route::has('register'))<a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Create account</a>@endif
        </div>
    </div>
    <div class="ix-top__inner ix-top__inner--nav">
        @include('layouts.public-tabs')
    </div>
</header>
