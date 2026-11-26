{{-- R-71: on the Cookie Policy page, the visitor's current choice about the optional dot_src cookie, and a way to change it. --}}
@php($choice = \App\Support\CookieConsent::choice(request()))
<section class="ix-cookie-choice" aria-labelledby="your-choice">
    <h2 id="your-choice">Your choice</h2>
    @if (session('cookie-choice-saved'))
        <p class="ix-alert" role="status">Saved. {{ session('cookie-choice-saved') === 'accepted' ? 'The optional cookie is allowed on this browser.' : 'The optional cookie is not set on this browser, and any one already set has been removed.' }}</p>
    @endif
    <p>
        @if ($choice === \App\Support\CookieConsent::ACCEPTED)
            On this browser you have allowed the optional <code>dot_src</code> cookie.
        @elseif ($choice === \App\Support\CookieConsent::DECLINED)
            On this browser you have declined the optional <code>dot_src</code> cookie.
        @else
            You have not chosen yet on this browser, so the optional <code>dot_src</code> cookie is not set.
        @endif
        You can change this at any time.
    </p>
    <form method="POST" action="{{ route('cookie-consent') }}" class="ix-cookies__actions">
        @csrf
        <button type="submit" name="choice" value="declined" class="ix-btn" @disabled($choice === \App\Support\CookieConsent::DECLINED)>Decline the optional cookie</button>
        <button type="submit" name="choice" value="accepted" class="ix-btn" @disabled($choice === \App\Support\CookieConsent::ACCEPTED)>Allow the optional cookie</button>
    </form>
</section>
