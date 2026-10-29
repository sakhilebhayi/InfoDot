<x-app-layout>
    @php($flash = session('reputation_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Your progress</h1>
            <p class="ix-lede">Your knowledge score is switched off. Nothing is being worked out or stored about it.</p>
        </div>
    </div>
    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif
    <form method="POST" action="{{ route('reputation.preference') }}">
        @csrf
        <input type="hidden" name="reputation" value="1">
        <button class="ix-btn ix-btn--primary" type="submit">Switch my score on</button>
    </form>
</x-app-layout>
