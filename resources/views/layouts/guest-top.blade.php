{{-- The top bar for visitors who are not signed in (R-33): the brand, the directory, and the
     two ways in. Nothing here assumes an account. --}}
<header class="ix-top">
    <div class="ix-top__inner">
        <a href="{{ url('/') }}" class="ix-brand" aria-label="InfoDot home">
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/brand/logo_white.png') }}" alt="InfoDot" width="64" height="26">
        </a>
        <nav class="ix-tabs" aria-label="Primary">
            <a href="{{ route('businesses.index') }}" class="ix-tab" data-area="people" @if(request()->routeIs('businesses.*', 'business.show')) aria-current="page" @endif>Businesses</a>
        </nav>
        <div class="ix-guestnav">
            <a href="{{ route('login') }}" class="ix-btn ix-btn--ghost ix-btn--sm">Sign in</a>
            @if (Route::has('register'))<a href="{{ route('register') }}" class="ix-btn ix-btn--primary ix-btn--sm">Join InfoDot</a>@endif
        </div>
    </div>
</header>
