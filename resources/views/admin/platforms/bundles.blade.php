<x-app-layout title="Bundles">
    @include('admin.platforms._tabs', ['current' => 'bundles'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Bundles</h1>
            <p class="ix-lede">A bundle is a named set of platforms sold together at one price. Like a plan, it is on sale only after its price is approved.</p>
        </div>
    </div>

    @include('admin.platforms._flash')

    <section class="ix-panel ix-panel--clip" aria-label="Bundles">
        @if ($bundles->isEmpty())
            <p class="ix-hint ix-panel__body">No bundle exists. Create one below when the owner has decided which platforms are sold together and at what price.</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Bundle</th><th>Platforms</th><th>Price</th><th>On sale</th></tr></thead>
                    <tbody>
                        @foreach ($bundles as $bundle)
                            @php $onSale = $bundle->active && $bundle->approved_at && $bundle->amount_minor !== null && $bundle->currency !== null; @endphp
                            <tr>
                                <td><a class="title" href="{{ route('admin.platforms.bundle', $bundle) }}">{{ $bundle->name }}</a> <span class="ix-ref">{{ $bundle->slug }}</span></td>
                                <td class="ix-adm__soft" data-label="Platforms">{{ $bundle->items->pluck('platform_key')->map(fn ($key) => $choices->firstWhere('key', $key)?->name ?? $key)->implode(', ') }}</td>
                                <td data-label="Price">{{ \App\Support\Money::format($bundle->amount_minor, $bundle->currency) }}@if ($bundle->amount_minor !== null && $bundle->interval) a {{ $bundle->interval }}@endif</td>
                                <td>
                                    @if ($onSale)<span class="ix-chip ix-chip--done">On sale</span>
                                    @elseif ($bundle->approved_at)<span class="ix-chip">Approved, switched off</span>
                                    @else<span class="ix-chip ix-chip--open">Price not approved</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ix-panel ix-vgap" aria-labelledby="new-h">
        <div class="ix-panel__head"><h2 id="new-h">Create a bundle</h2></div>
        <div class="ix-panel__body">
            <form method="post" action="{{ route('admin.platforms.bundles.store') }}" class="ix-form">
                @csrf
                @include('admin.platforms._bundle-fields', ['bundle' => null, 'p' => 'new'])
                @include('admin.platforms._reason', ['id' => 'new-reason'])
                <p class="ix-hint">A new bundle is a draft. It goes on sale only after you approve its price on the next page and switch it on.</p>
                <div><button type="submit" class="ix-btn ix-btn--primary">Create the bundle</button></div>
            </form>
        </div>
    </section>
</x-app-layout>
