<x-app-layout>
    @php($isSolved = (int) $question->status === 1)

    <a href="{{ route('questions') }}" class="ix-back"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>Questions</a>

    <article class="ix-panel">
        <div class="ix-panel__body" style="padding:24px">
            <span class="ix-state {{ $isSolved ? 'ix-state--done' : 'ix-state--open' }}">{{ $isSolved ? 'Solved' : 'Unsolved' }}</span>
            <h1 class="ix-h1" style="margin-top:8px;font-size:24px">{{ $question->question }}</h1>
            <p class="ix-meta" style="margin-top:8px">
                <span>Asked by <b><a href="{{ route('profile.show', ['id' => $question->user->id]) }}">{{ $question->user->name }}</a></b></span>
                <span>{{ $question->created_at->diffForHumans() }}</span>
            </p>
            @if (filled($question->description))
                <p class="ix-prose" style="margin:20px 0 0">{{ $question->description }}</p>
            @endif
        </div>

        <div style="border-top:1px solid var(--ix-rule);padding:24px">
            <livewire:comments :model="$question" :question="$question" />
        </div>
    </article>
</x-app-layout>
