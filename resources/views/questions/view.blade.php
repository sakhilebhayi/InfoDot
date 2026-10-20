<x-app-layout>
    @php
        $ref = \App\Support\Reference::for($question);
        $solved = (int) $question->status === 1;
    @endphp

    <div class="ix-detail">
        <article class="ix-detail__main" x-data="{ copied: false }">
            <div class="ix-itemhead">
                <span class="ix-ref ix-ref--lg" aria-label="Reference {{ $ref }}">{{ $ref }}</span>
                <button type="button" class="ix-copy"
                        @click="navigator.clipboard.writeText('{{ $ref }}').then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                    <span class="material-symbols-rounded" aria-hidden="true" x-text="copied ? 'check' : 'content_copy'">content_copy</span>
                    <span x-text="copied ? 'Copied' : 'Copy reference'">Copy reference</span>
                </button>
            </div>

            <p class="ix-detail__kind"><span class="ix-q__state {{ $solved ? 'is-done' : 'is-open' }}">{{ $solved ? 'Solved' : 'Waiting for an answer' }}</span></p>
            <h1 class="ix-detail__title">{{ $question->question }}</h1>

            @if (filled($question->description))
                <div class="ix-statement"><p class="ix-prose">{{ $question->description }}</p></div>
            @endif

            <livewire:answers :question="$question" />

            <livewire:comments :model="$question" :question="$question" />
        </article>

        <aside class="ix-detail__rail" aria-label="About this question">
            <section class="ix-facts">
                <h2>Details</h2>
                <dl>
                    <div><dt>Asked by</dt><dd><a href="{{ route('profile.show', ['id' => $question->user->id]) }}">{{ $question->user->name }}</a></dd></div>
                    <div><dt>Asked</dt><dd>{{ $question->created_at->diffForHumans() }}</dd></div>
                    <div><dt>Visible to</dt><dd>{{ $question->isPublic() ? 'Everyone' : 'Your company' }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $solved ? 'Solved' : 'Waiting for an answer' }}</dd></div>
                </dl>
            </section>

            @if ($related->isNotEmpty())
                <section class="ix-related">
                    <h2>More like this</h2>
                    <ul>
                        @foreach ($related as $other)
                            <li>
                                <a href="{{ route('questions.view', ['qid' => $other->id]) }}">{{ $other->question }}</a>
                                <span class="ix-ref">{{ \App\Support\Reference::for($other) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <a href="{{ route('seek') }}" class="ix-btn">Ask another question</a>
        </aside>
    </div>
</x-app-layout>
