<x-app-layout :title="$bundle->name">
    @include('admin.platforms._tabs', ['current' => 'bundles'])

    @php $onSale = $bundle->active && $bundle->approved_at && $bundle->amount_minor !== null && $bundle->currency !== null; @endphp
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $bundle->name }}</h1>
            <p class="ix-lede">A bundle of {{ $bundle->items->count() }} platforms <span class="ix-ref">{{ $bundle->slug }}</span></p>
        </div>
        <a href="{{ route('admin.platforms.bundles') }}" class="ix-btn ix-btn--ghost">All bundles</a>
    </div>

    @include('admin.platforms._flash')

    <div class="ix-adm__split">
        <section class="ix-panel" aria-labelledby="bundle-h">
            <div class="ix-panel__head"><h2 id="bundle-h">The bundle</h2></div>
            <div class="ix-panel__body">
                <form method="post" action="{{ route('admin.platforms.bundle.update', $bundle) }}" class="ix-form">
                    @csrf
                    @include('admin.platforms._bundle-fields', ['bundle' => $bundle, 'p' => 'bundle'])
                    @if ($bundle->approved_at)<p class="ix-hint">Changing the price or the platforms inside withdraws the approval, and the bundle goes off sale until it is approved again.</p>@endif
                    @include('admin.platforms._reason', ['id' => 'bundle-reason'])
                    <div><button type="submit" class="ix-btn ix-btn--primary">Save the bundle</button></div>
                </form>
            </div>
        </section>

        <div class="ix-adm__side">
            @include('admin.platforms._approval', [
                'of' => $bundle, 'kind' => 'bundle', 'onSale' => $onSale, 'approval' => $events->firstWhere('type', 'price_approved'),
                'approve' => route('admin.platforms.bundle.decide', [$bundle, 'approve']), 'withdraw' => route('admin.platforms.bundle.decide', [$bundle, 'withdraw']),
            ])

            <section class="ix-panel" aria-labelledby="held-h">
                <div class="ix-panel__head"><h2 id="held-h">Companies on this bundle</h2><span class="ix-hint">{{ $holders }}</span></div>
                <p class="ix-hint ix-panel__body">A change here never alters what a company already holds.</p>
            </section>

            <section class="ix-panel" aria-labelledby="hist-h">
                <div class="ix-panel__head"><h2 id="hist-h">Changes</h2><span class="ix-hint">{{ $events->count() }}</span></div>
                <div class="ix-panel__body">@include('admin.platforms._trail', ['events' => $events])</div>
            </section>
        </div>
    </div>
</x-app-layout>
