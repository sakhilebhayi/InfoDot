{{-- The places a visitor can go without an account. One list, used by both public top bars. --}}
<nav class="ix-tabs" aria-label="About InfoDot">
    <a href="{{ route('faqs') }}" class="ix-tab" @if (request()->routeIs('faqs')) aria-current="page" @endif>Questions people ask</a>
    <a href="{{ route('solution_search_results') }}" class="ix-tab" data-area="knowledge" @if (request()->routeIs('solution_search_results')) aria-current="page" @endif>Browse public answers</a>
    <a href="{{ route('businesses.index') }}" class="ix-tab" data-area="people" @if (request()->routeIs('businesses.*', 'business.show')) aria-current="page" @endif>Businesses</a>
</nav>
