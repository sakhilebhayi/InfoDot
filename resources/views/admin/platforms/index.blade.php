<x-app-layout title="Platform admin">
    @include('admin.platforms._tabs', ['current' => 'catalogue'])

    @php
        $status = [
            'operational' => ['Operational', 'ix-chip--done'], 'degraded' => ['Degraded', 'ix-chip--open'], 'maintenance' => ['Maintenance', 'ix-chip--open'],
            'unavailable' => ['Unavailable', 'ix-chip--open'], 'development' => ['In development', ''], 'not_launched' => ['Not launched', ''],
        ];
        $unpriced = $entries->filter(fn ($e) => ! $e->is_core && $e->discoverable && ! ($plans[$e->key] ?? collect())->contains->isSellable())->count();
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Catalogue</h1>
            <p class="ix-lede">Every platform in the ecosystem: whether it is working, whether businesses are shown it, and the plans it is sold on. {{ $unpriced }} {{ $unpriced === 1 ? 'platform has' : 'platforms have' }} no approved price, so {{ $unpriced === 1 ? 'it is' : 'they are' }} free for a company to switch on until one is approved.</p>
        </div>
    </div>

    @include('admin.platforms._flash')

    <section class="ix-panel ix-panel--clip" aria-label="Platforms">
        <div class="ix-tablewrap">
            <table class="ix-table ix-adm__table">
                <thead><tr><th>Platform</th><th>Working</th><th>Shown to businesses</th><th>Pricing</th><th class="num">Companies using it</th></tr></thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php
                            $own = ($plans[$entry->key] ?? collect());
                            $selling = $own->filter->isSellable();
                            [$label, $tone] = $status[$entry->operational_status] ?? [$entry->operational_status, ''];
                        @endphp
                        <tr>
                            <td>
                                <a class="title" href="{{ route('admin.platforms.platform', $entry) }}">{{ $entry->name }}</a>
                                <span class="ix-ref">{{ $entry->key }}</span>
                                @unless (in_array($entry->key, $registry, true))<span class="ix-chip ix-chip--open">Not in the registry</span>@endunless
                            </td>
                            <td data-label="Working"><span class="ix-chip {{ $tone }}">{{ $label }}</span></td>
                            <td data-label="Shown to businesses">{{ $entry->discoverable ? 'Yes' : 'Hidden' }}</td>
                            <td data-label="Pricing">
                                @if ($entry->is_core)
                                    <span class="ix-adm__soft">Core: included for every company</span>
                                @elseif ($selling->isNotEmpty())
                                    {{ $selling->count() }} {{ $selling->count() === 1 ? 'plan' : 'plans' }} on sale @if ($own->count() > $selling->count()) and {{ $own->count() - $selling->count() }} not on sale @endif
                                @elseif ($own->isNotEmpty())
                                    <span class="ix-chip ix-chip--open">Pricing not approved</span> <span class="muted">{{ $own->count() }} {{ $own->count() === 1 ? 'draft plan' : 'draft plans' }}</span>
                                @else
                                    <span class="ix-chip">Pricing not configured</span>
                                @endif
                            </td>
                            <td class="num" data-label="Companies using it">{{ $entry->is_core ? 'All' : ($held[$entry->key] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <p class="ix-hint ix-vgap">The core set (InfoDot, Dot.Files, Dot.docs, Dot.Tasks, Dot.Finance) is the owner's decision and cannot be changed here. A platform missing from this list is added by running <code>platforms:sync</code> on the host.</p>
</x-app-layout>
