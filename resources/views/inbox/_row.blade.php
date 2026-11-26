{{-- One conversation in an inbox list. The whole row is the link; the subject is its accessible name.
     $href, $subject, $who, $meta (array of short phrases), $plate (logo url or null), $initial, $unread, $unreadLabel,
     $status, $statusTone ('open' | 'done' | null), $preview (string or null), $previewMine, $updated, $of (for the demo mark). --}}
<li @class(['ix-inrow', 'is-unread' => $unread])>
    <span class="ix-inrow__plate" aria-hidden="true">
        @if ($plate)<img src="{{ $plate }}" alt="" width="40" height="40" loading="lazy">@else{{ mb_strtoupper(mb_substr(trim((string) $initial), 0, 1)) ?: '?' }}@endif
    </span>
    <div class="ix-inrow__main">
        <p class="ix-inrow__who"><b>{{ $who }}</b>@foreach ($meta as $bit)<span>{{ $bit }}</span>@endforeach <x-demo-mark :of="$of" /></p>
        <a href="{{ $href }}" class="ix-inrow__subject">{{ $subject }}</a>
        @if ($preview !== null)
            <p class="ix-inrow__preview">@if ($previewMine)<span class="ix-inrow__you">{{ $previewPrefix ?? 'You:' }}</span> @endif{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $preview), 160) }}</p>
        @endif
    </div>
    <div class="ix-inrow__side">
        <time datetime="{{ $updated->toIso8601String() }}" title="{{ $updated->format('j M Y, H:i') }}">{{ $updated->isToday() ? $updated->format('H:i') : ($updated->isCurrentYear() ? $updated->format('j M') : $updated->format('j M Y')) }}</time>
        <span class="ix-inrow__chips">
            @if ($unread)<span class="ix-inrow__new"><span class="ix-inrow__dot" aria-hidden="true"></span>{{ $unreadLabel }}</span>@endif
            <span @class(['ix-chip', 'ix-chip--open' => $statusTone === 'open', 'ix-chip--done' => $statusTone === 'done'])>{{ $status }}</span>
        </span>
    </div>
</li>
