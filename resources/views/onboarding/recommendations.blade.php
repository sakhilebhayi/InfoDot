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

    @if ($firstFour->isNotEmpty())
        <section class="ix-panel ix-vgap" aria-labelledby="first-four-title" data-first-four>
            <div class="ix-panel__body">
                <h2 class="ix-h2" id="first-four-title">Your first {{ $firstFour->count() === 1 ? 'platform' : $firstFour->count().' platforms' }}</h2>
                <p class="ix-hint">These are free to switch on today and not on yet for {{ $team->name }}. Switching one on costs nothing and you can switch it off again.</p>
                <form method="POST" action="{{ route('onboarding.platforms.activate') }}">
                    @csrf
                    <input type="hidden" name="company" value="{{ $team->uuid }}">
                    <ul class="ix-list">
                        @foreach ($firstFour as $pick)
                            <li>
                                <label>
                                    @if ($canSwitchOn)<input type="checkbox" name="platforms[]" value="{{ $pick['key'] }}" checked>@endif
                                    <strong>{{ $pick['name'] }}</strong> {{ $pick['tagline'] }}
                                </label>
                                <span class="ix-hint">{{ implode(' ', $pick['reasons']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($canSwitchOn)
                        <button class="ix-btn ix-btn--primary">Switch on the ticked platforms</button>
                    @else
                        <p class="ix-hint">Only the owner or an administrator can switch platforms on. Ask them to open this page.</p>
                    @endif
                </form>
            </div>
        </section>
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
