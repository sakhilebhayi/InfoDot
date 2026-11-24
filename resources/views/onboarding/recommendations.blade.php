<x-app-layout title="Where to start">
    <div class="ix-page">
        <h1 class="ix-page__title">Where to start, {{ $team->name }}</h1>
        <p class="ix-page__lede">These platforms fit what you told us. Each one says why it is here.</p>

        @if (session('status'))
            <div class="ix-note" role="status"><p>{{ session('status') }}</p></div>
        @endif

        <ol class="ix-list">
            @foreach ($recommendations as $item)
                <li class="ix-card">
                    <h2>{{ $item['name'] }}</h2>
                    @if ($item['tagline'] !== '')<p>{{ $item['tagline'] }}</p>@endif
                    <ul>
                        @foreach ($item['why'] as $reason)
                            <li class="ix-hint">{{ $reason }}</li>
                        @endforeach
                    </ul>
                    <a class="ix-btn ix-btn--ghost" href="{{ route('platforms.show', $item['key']) }}">Explore {{ $item['name'] }}</a>
                </li>
            @endforeach
        </ol>

        <form method="POST" action="{{ route('onboarding.recommendations.update') }}" class="ix-entry__form">
            @csrf
            @include('onboarding._objectives', ['selected' => $selected])
            <button class="ix-btn ix-btn--primary">Update the list</button>
        </form>

        <p><a href="{{ route('ecosystem.widget') }}">See every platform</a></p>
    </div>
</x-app-layout>
