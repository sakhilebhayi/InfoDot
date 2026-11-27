<section class="lp-hero hd" data-hd data-scene="b" aria-labelledby="hero-title">
    <div class="hd__stage" aria-hidden="true">
        <div class="hd__scene hd__scene--a" data-scene-id="a">
            {{-- R-75 P3: WebP first (about 60% lighter on a shared host that serves files slowly), the JPEG stays the fallback. --}}
            <picture>
            <source type="image/webp" srcset="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-skyline-960.webp') }} 960w, {{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-skyline-1920.webp') }} 1920w" sizes="100vw">
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-skyline-1920.jpg') }}" srcset="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-skyline-960.jpg') }} 960w, {{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-skyline-1920.jpg') }} 1920w" sizes="100vw" alt="" width="1920" height="1280" decoding="async">
            </picture>
        </div>
        <div class="hd__scene hd__scene--b" data-scene-id="b">
            <div class="hd__ground">
                <span class="hd__ref">INFO-S-000021</span>
                <span class="ix-stamp hd__stamp">Solved</span>
            </div>
            <figure class="hd__photo">
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/rosebank-1600.jpg') }}" srcset="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/rosebank-800.jpg') }} 800w, {{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/rosebank-1600.jpg') }} 1600w" sizes="(max-width: 900px) 100vw, 50vw" alt="" width="1600" height="1067" decoding="async" fetchpriority="high">
            </figure>
        </div>
        <div class="hd__scene hd__scene--c" data-scene-id="c">
            <div class="hd__ground"></div>
            <ul class="hd__tiles" role="presentation">
                <li class="hd__tile hd__tile--1"><img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/bokaap-street-800.jpg') }}" alt="" width="800" height="533" decoding="async"><span class="pg-plate" style="--pg-tone: var(--ix-knowledge)">Knowledge</span></li>
                <li class="hd__tile hd__tile--2"><img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/kalkbay-sign-800.jpg') }}" alt="" width="800" height="533" decoding="async"><span class="pg-plate pg-plate--mustard" style="--pg-tone: var(--ix-platforms)">Tools</span></li>
                <li class="hd__tile hd__tile--3"><img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/vineyard-800.jpg') }}" alt="" width="800" height="533" decoding="async"><span class="pg-plate" style="--pg-tone: var(--ix-people)">Connections</span></li>
            </ul>
        </div>
    </div>

    <div class="hd__grid">
@include('partials.home-hero-copy')
    </div>

    <div class="hd__ledger">@include('partials.home-hero-ledger')</div>

    @include('partials.home-hero-panel')

    <div class="hd__ctl" role="group" aria-label="Hero pictures" data-hd-ctl hidden>
        <button type="button" class="hd__pause" data-hd-pause aria-pressed="false" aria-label="Pause the changing pictures"><svg class="hd__ico hd__ico--pause" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="6" y="5" width="4" height="14" rx="1" fill="currentColor"/><rect x="14" y="5" width="4" height="14" rx="1" fill="currentColor"/></svg><svg class="hd__ico hd__ico--play" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg></button>
        <button type="button" class="hd__dot" data-hd-dot="a" aria-label="Picture 1 of 3: the market photograph"></button>
        <button type="button" class="hd__dot" data-hd-dot="b" aria-label="Picture 2 of 3: the library beside the photograph" aria-current="true"></button>
        <button type="button" class="hd__dot" data-hd-dot="c" aria-label="Picture 3 of 3: knowledge, tools and connections"></button>
    </div>
</section>
