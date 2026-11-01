<x-app-layout title="How featuring works">
    @push('meta')
        <meta name="description" content="The rules InfoDot uses to feature businesses in its directory, and what featuring does not mean.">
    @endpush

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">How featuring works</h1>
            <p class="ix-lede">A few businesses are shown at the top of the directory for a while. These are the rules, all of them.</p>
        </div>
        <a href="{{ route('businesses.index') }}" class="ix-btn ix-btn--ghost">Back to Businesses</a>
    </div>

    <div class="ix-rules">
        <section class="ix-bizsec">
            <div class="ix-bizsec__head"><span class="ix-bizsec__num">01</span><h2 class="ix-bizsec__lbl">What featuring is not</h2></div>
            <p class="ix-biz__prose">Featuring is a placement. It is not a rating, a recommendation, a review of quality, or a verification. A featured business may or may not be verified; its verification shows separately, and InfoDot verification itself only confirms who a business is. Nothing about a featured business’s services, prices or conduct is checked because it is featured.</p>
        </section>
        <section class="ix-bizsec">
            <div class="ix-bizsec__head"><span class="ix-bizsec__num">02</span><h2 class="ix-bizsec__lbl">Who can be featured</h2></div>
            <p class="ix-biz__prose">A business that meets every one of these, checked when it asks and again when a reviewer decides:</p>
            <ul class="ix-trust__checks">
                @foreach ($rules as $rule)<li>{{ $rule }}</li>@endforeach
            </ul>
        </section>
        <section class="ix-bizsec">
            <div class="ix-bizsec__head"><span class="ix-bizsec__num">03</span><h2 class="ix-bizsec__lbl">How it is decided</h2></div>
            <p class="ix-biz__prose">The business’s owner or an administrator asks from its profile page. A reviewer who does not belong to the business grants a placement of up to {{ \App\Models\BusinessFeature::MAX_DAYS }} days, or declines with a reason. A placement ends early if the business stops meeting the rules. At most {{ \App\Services\Directory\FeaturedPlacement::SHOWN }} featured businesses show at once, in an order that changes every day.</p>
        </section>
        <section class="ix-bizsec">
            <div class="ix-bizsec__head"><span class="ix-bizsec__num">04</span><h2 class="ix-bizsec__lbl">Payment</h2></div>
            <p class="ix-biz__prose">Featuring is not sold today. If paid featuring is introduced, it will be labelled as paid wherever it appears and these rules will say what it costs before anyone is charged.</p>
        </section>
    </div>
</x-app-layout>
