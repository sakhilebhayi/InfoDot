<footer class="ix-foot">
    <span>&copy; {{ date('Y') }} InfoDot</span>
    @if (auth()->guest() && ! request()->routeIs('welcome'))
        {{-- R-75 add-on: the brand promise, small, on the public pages only (the homepage already says it in its own band). --}}
        <span class="ix-foot__promise">Business is hard enough. Getting the support you need shouldn't be.</span>
    @endif
    <nav aria-label="Legal">
        <a href="{{ route('features') }}">What it does</a>
        <a href="{{ route('about') }}">About</a>
        <a href="{{ route('faqs') }}">Questions</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('policy') }}">Privacy</a>
        <a href="{{ route('cookies') }}">Cookies</a>
        <a href="{{ route('terms') }}">Terms</a>
        <a href="{{ route('security') }}">Security</a>
        <a href="{{ route('about') }}#photo-credits">Photo credits</a>
    </nav>
</footer>
