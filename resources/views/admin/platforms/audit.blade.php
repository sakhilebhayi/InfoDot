<x-app-layout title="Audit trail">
    @include('admin.platforms._tabs', ['current' => 'audit'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Audit trail</h1>
            <p class="ix-lede">Every change to the catalogue, a plan, a bundle or the administrator role: what changed, who changed it and why. Entries cannot be edited or removed. A company's own history is on its page under Companies.</p>
        </div>
    </div>

    <nav class="ix-filters" aria-label="What the entry is about">
        <a class="ix-pill" href="{{ route('admin.platforms.audit') }}" @if ($about === null) aria-current="true" @endif>Everything</a>
        @foreach (['platform' => 'Platforms', 'plan' => 'Plans', 'bundle' => 'Bundles', 'administrator' => 'Administrators', 'outbox' => 'Deliveries'] as $value => $text)
            <a class="ix-pill" href="{{ route('admin.platforms.audit', ['about' => $value]) }}" @if ($about === $value) aria-current="true" @endif>{{ $text }}</a>
        @endforeach
    </nav>

    <section class="ix-panel ix-vgap" aria-label="Audit entries">
        <div class="ix-panel__body">@include('admin.platforms._trail', ['events' => $events, 'withSubject' => true])</div>
    </section>

    @include('partials.pager', ['pager' => $events, 'label' => 'Audit trail'])
</x-app-layout>
