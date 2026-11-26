{{-- R-70: InfoDot's aim, in the owner's words (2026-11-26). It is an aim, stated as one, never a count: no numeral plate and no
     figure dressed as a statistic. Shared by the homepage and the Business tools page. --}}
@php($id = $id ?? 'aim')
<section class="hp-aim" aria-labelledby="{{ $id }}-title">
    <h2 id="{{ $id }}-title" class="hp-aim__title" data-reveal>Our aim is to help <span class="hp-aim__mark">one million businesses every year</span> reach the goals they set for themselves.</h2>
    <p class="hp-aim__lede" data-reveal>We get there by putting the knowledge, tools and connections a business needs within its reach. Yours could be one of them.</p>
</section>
