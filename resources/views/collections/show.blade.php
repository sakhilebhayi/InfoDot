<x-app-layout :title="$collection->name">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $collection->name }}</h1>
            <p class="ix-lede">{{ $items->count() }} {{ \Illuminate\Support\Str::plural('business', $items->count()) }}. Only you see this collection.</p>
        </div>
        <a href="{{ route('collections') }}" class="ix-btn ix-btn--ghost">All collections</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    @if ($items->isEmpty())
        <div class="ix-empty"><h2>Nothing saved here yet</h2><p>Save a business to “{{ $collection->name }}” from its page.</p></div>
    @else
        <ul class="ix-collection">
            @foreach ($items as $item)
                <li>
                    @if ($item->team && $item->team->isVisibleTo($viewer))
                        <div class="ix-collection__card">
                            <ul class="ix-dir__results">@include('businesses._card', ['team' => $item->team, 'why' => []])</ul>
                        </div>
                    @else
                        <p class="ix-hint ix-collection__gone">A business you saved is no longer available to you.</p>
                    @endif
                    <div class="ix-collection__side">
                        @if ($item->note)<p class="ix-standing__msg">{{ $item->note }}</p>@endif
                        <p class="ix-hint">Saved {{ $item->created_at->format('j M Y') }}</p>
                        <form method="post" action="{{ route('collections.remove', [$collection, $item]) }}">@csrf @method('DELETE')<button class="ix-btn ix-btn--ghost ix-btn--sm">Take out</button></form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <section class="ix-panel ix-vgap" aria-labelledby="manage-h">
        <div class="ix-panel__head"><h2 id="manage-h">Collection</h2></div>
        <div class="ix-panel__body ix-vactions">
            <form method="post" action="{{ route('collections.update', $collection) }}" class="ix-form ix-saveform">@csrf
                <label class="ix-label" for="name">Name</label><input class="ix-input" id="name" name="name" minlength="2" maxlength="80" required value="{{ $collection->name }}">
                <button class="ix-btn">Rename</button>
            </form>
            <details><summary class="ix-btn ix-btn--danger ix-btn--sm">Delete this collection</summary>
                <form method="post" action="{{ route('collections.destroy', $collection) }}" class="ix-vgap">@csrf @method('DELETE')<button class="ix-btn ix-btn--danger ix-btn--sm">Yes, delete “{{ $collection->name }}”</button></form>
            </details>
        </div>
    </section>
</x-app-layout>
