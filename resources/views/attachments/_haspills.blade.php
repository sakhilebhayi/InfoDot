{{-- ?has= filter pills (R-15). Include with $route, $filterSlug (current topic) and $filterHas. --}}
@php
    $base = fn (?string $has) => $route.'?'.http_build_query(array_filter(['topic' => $filterSlug ?: null, 'has' => $has]));
@endphp
<nav class="ix-pills" aria-label="Filter by what posts carry" style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0">
    <a class="ix-pill" href="{{ $base(null) }}" @if ($filterHas === '') aria-current="true" style="background:var(--ix-signal-wash)" @endif>Everything</a>
    <a class="ix-pill" href="{{ $base('images') }}" @if ($filterHas === 'images') aria-current="true" style="background:var(--ix-signal-wash)" @endif>With images</a>
    <a class="ix-pill" href="{{ $base('documents') }}" @if ($filterHas === 'documents') aria-current="true" style="background:var(--ix-signal-wash)" @endif>With documents</a>
    <a class="ix-pill" href="{{ $base('videos') }}" @if ($filterHas === 'videos') aria-current="true" style="background:var(--ix-signal-wash)" @endif>With video</a>
</nav>
