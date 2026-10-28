{{-- Previous / next for one list. Pass the paginator as $pager and a name for the list as $label (for screen readers). --}}
@if ($pager->hasPages())
    <nav class="ix-pager" aria-label="{{ $label }} pages">
        @if ($pager->previousPageUrl())
            <a class="ix-btn" href="{{ $pager->previousPageUrl() }}" rel="prev"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>Previous</a>
        @endif
        <span class="ix-pager__where">Page {{ $pager->currentPage() }} of {{ $pager->lastPage() }}</span>
        @if ($pager->nextPageUrl())
            <a class="ix-btn" href="{{ $pager->nextPageUrl() }}" rel="next">Next<span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a>
        @endif
    </nav>
@endif
