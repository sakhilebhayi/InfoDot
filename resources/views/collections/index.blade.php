<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Collections</h1>
            <p class="ix-lede">Your own lists of businesses — suppliers, contractors, businesses to contact later. Only you see them; the businesses are not told. Save a business from its page.</p>
        </div>
        <a href="{{ route('businesses.index') }}" class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">storefront</span>Find businesses</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif

    @if ($collections->isEmpty())
        <div class="ix-empty">
            <h2>No collections yet</h2>
            <p>Open a business’s page and use “Save” to start one. People often keep {{ mb_strtolower(implode(', ', array_slice(\App\Models\BusinessCollection::SUGGESTED, 0, 4))) }} and businesses to contact later.</p>
        </div>
    @else
        <ul class="ix-inboxlinks">
            @foreach ($collections as $c)
                <li><a href="{{ route('collections.show', $c) }}"><b>{{ $c->name }}</b><span>{{ $c->items_count }} {{ \Illuminate\Support\Str::plural('business', $c->items_count) }}</span></a></li>
            @endforeach
        </ul>
    @endif
</x-app-layout>
