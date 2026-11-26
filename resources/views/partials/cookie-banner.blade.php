{{-- R-71: the cookie banner. Shown to a signed-out visitor who has not chosen yet, in the page's own flow directly under the top bar,
     so it never sits over the page's controls and never blocks it; nothing optional is set until they choose. Accept and Decline carry equal weight. Without JavaScript the form posts and the page comes
     back; with it (resources/js/cookie-banner.js) the choice is saved in the background and the banner leaves. --}}
@if (\App\Support\CookieConsent::shouldAsk(request()))
    <section class="ix-cookies" role="region" aria-labelledby="cookies-title" data-cookie-banner>
        <div class="ix-cookies__inner">
            <div class="ix-cookies__text">
                <h2 id="cookies-title" class="ix-cookies__title">One optional cookie</h2>
                <p>InfoDot uses the cookies it needs to sign you in and keep forms safe. With your OK it also keeps one for 30 days that records which link or campaign brought you here, so we know which ones work. It is never shared or used to follow you elsewhere. <a href="{{ route('cookies') }}">Cookie Policy</a></p>
            </div>
            <form method="POST" action="{{ route('cookie-consent') }}" class="ix-cookies__actions" data-cookie-form>
                @csrf
                <button type="submit" name="choice" value="declined" class="ix-btn">Decline</button>
                <button type="submit" name="choice" value="accepted" class="ix-btn">Accept</button>
            </form>
        </div>
    </section>
@endif
