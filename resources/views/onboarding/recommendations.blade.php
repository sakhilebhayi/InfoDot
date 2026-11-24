<x-app-layout title="Where to start">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Where to start, {{ $team->name }}</h1>
            <p class="ix-lede">These platforms fit what you told us. Each one says why it is here.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="ix-previewbar" role="status"><p>{{ session('status') }}</p></div>
    @endif

    <section class="ix-panel ix-panel--clip" aria-label="Recommended platforms">
        <div class="ix-tablewrap">
            <table class="ix-table ix-adm__table">
                <thead><tr><th>Platform</th><th>What it does</th><th>Why it is here</th><th></th></tr></thead>
                <tbody>
                    @foreach ($recommendations as $item)
                        <tr>
                            <td class="title">{{ $item['name'] }}</td>
                            <td data-label="What it does">{{ $item['tagline'] }}</td>
                            <td class="muted" data-label="Why it is here">{!! collect($item['why'])->map(fn ($r) => e($r))->implode('<br>') !!}</td>
                            <td><a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('platforms.show', $item['key']) }}">Explore</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <form method="POST" action="{{ route('onboarding.recommendations.update') }}" class="ix-panel ix-vgap">
        @csrf
        <div class="ix-panel__body">
            @include('onboarding._objectives', ['selected' => $selected])
            <button class="ix-btn ix-btn--primary">Update the list</button>
        </div>
    </form>

    <p class="ix-vgap"><a href="{{ route('ecosystem.widget') }}">See every platform</a></p>
</x-app-layout>
