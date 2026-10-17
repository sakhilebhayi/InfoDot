<x-app-layout>
    @php($steps = \App\Models\Steps::where('solution_id', $solution->id)->get())
    @php($ref = \App\Support\Reference::for($solution))


    <div class="ix-split" style="grid-template-columns:minmax(0,1.1fr) minmax(0,1fr)">
        <article class="ix-panel" x-data="{ copied: false }">
            <div class="ix-panel__body" style="padding:24px">
                <div class="ix-itemhead">
                    <span class="ix-ref ix-ref--lg" aria-label="Reference {{ $ref }}">{{ $ref }}</span>
                    <button type="button" class="ix-copy"
                            @click="navigator.clipboard.writeText('{{ $ref }}').then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                        <span class="material-symbols-rounded" aria-hidden="true" x-text="copied ? 'check' : 'content_copy'">content_copy</span>
                        <span x-text="copied ? 'Copied' : 'Copy reference'">Copy reference</span>
                    </button>
                </div>
                <h1 class="ix-h1" style="font-size:24px">{{ $solution->solution_title }}</h1>
                <p class="ix-meta">
                    <span>By <b><a href="{{ route('profile.show', ['id' => $solution->user->id]) }}">{{ $solution->user->name }}</a></b></span>
                    <span>{{ $solution->created_at->diffForHumans() }}</span>
                    @if ($solution->duration_type !== 'infinite')
                        <span>Takes <b class="ix-fig">{{ $solution->duration }}</b> {{ Str::plural(rtrim($solution->duration_type, 's'), $solution->duration) }}</span>
                    @endif
                </p>
                <p class="ix-prose" style="margin:20px 0 0">{{ $solution->solution_description }}</p>
                @if (filled($solution->tags))
                    <p class="ix-hint" style="margin-top:16px">Tags: {{ $solution->tags }}</p>
                @endif
            </div>
            <div style="border-top:1px solid var(--ix-rule);padding:24px">
                <livewire:comments :model="$solution" :solution="$solution" />
            </div>
        </article>

        <section class="ix-panel" aria-label="Steps">
            <div class="ix-panel__head"><span>Steps</span><x-ix.figure :value="$steps->count()" /></div>
            @forelse($steps as $step)
                <div class="ix-step">
                    <span class="ix-step__n ix-fig">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <div>
                        <h2>{{ $step->solution_heading }}</h2>
                        <p>{{ $step->solution_body }}</p>
                    </div>
                </div>
            @empty
                <p class="ix-panel__body" style="margin:0;color:var(--ix-text-3)">This solution has no written steps yet.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
