<x-app-layout>
    @php
        $ref = \App\Support\Reference::for($solution);
        $tags = collect(explode(',', (string) $solution->tags))->map(fn ($t) => trim($t))->filter()->values();
        $count = $steps->count();
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

            <p class="ix-detail__kind" x-data="{ chip: @js($solution->isVerified() ? 'Verified by moderator' : '') }" @solution-verification.window="chip = $event.detail.label">
                <span class="ix-q__state is-open" style="background:#1b2124;color:var(--ix-stencil)">Solution</span>
                <span class="ix-q__state is-done" x-show="chip" x-text="chip" @unless ($solution->isVerified()) x-cloak @endunless>@if ($solution->isVerified()) Verified by moderator @endif</span>
            </p>
            <h1 class="ix-detail__title">{{ $solution->solution_title }}</h1>

            <livewire:hidden-notice kind="solution" :item-id="$solution->id" />
            {{-- Written as plain text; nl2br keeps the author's paragraphs (official library entries rely on it). --}}
            <div class="ix-statement"><p class="ix-prose">{!! nl2br(e($solution->solution_description)) !!}</p></div>

            @include('attachments._list', ['attachments' => $solution->attachments])

            @if ($tags->isNotEmpty())
                <ul class="ix-tags" aria-label="Tags">@foreach ($tags as $tag)<li>{{ $tag }}</li>@endforeach</ul>
            @endif

            <section class="ix-proc" aria-labelledby="proc-h"
                     x-data="{ n: {{ $count }}, done: [], key: 'ix-proc-{{ $solution->id }}',
                        init() { try { this.done = JSON.parse(localStorage.getItem(this.key) || '[]') } catch (e) { this.done = [] } },
                        toggle(i) { this.done = this.done.includes(i) ? this.done.filter(x => x !== i) : [...this.done, i]; try { localStorage.setItem(this.key, JSON.stringify(this.done)) } catch (e) {} },
                        reset() { this.done = []; try { localStorage.removeItem(this.key) } catch (e) {} } }">
                <div class="ix-proc__head">
                    <h2 id="proc-h"><span class="ix-stencil-num">{{ str_pad((string) $count, 2, '0', STR_PAD_LEFT) }}</span> {{ Str::plural('step', $count) }}</h2>
                    @if ($count > 0)
                        <p class="ix-proc__progress" aria-live="polite">
                            <span x-text="done.length + ' of ' + n + ' done'">0 of {{ $count }} done</span>
                            <button type="button" class="ix-copy" x-show="done.length > 0" x-cloak @click="reset()">Start again</button>
                        </p>
                    @endif
                </div>
                @if ($count > 0)
                    <div class="ix-proc__bar" aria-hidden="true"><span :style="'transform:scaleX(' + (n ? done.length / n : 0) + ')'"></span></div>
                @endif

                <ol class="ix-proc__list">
                    @forelse ($steps as $step)
                        <li class="ix-pstep" :class="{ 'is-done': done.includes({{ $loop->index }}) }">
                            <button type="button" class="ix-pstep__n" role="checkbox" :aria-checked="done.includes({{ $loop->index }})" @click="toggle({{ $loop->index }})"
                                    aria-label="Mark step {{ $loop->iteration }} done">
                                <span class="ix-stencil-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="material-symbols-rounded" aria-hidden="true">check</span>
                            </button>
                            <div>
                                <h3>{{ $step->solution_heading }}</h3>
                                <p>{!! nl2br(e($step->solution_body)) !!}</p>
                                @include('attachments._list', ['attachments' => $step->attachments, 'compact' => true])
                            </div>
                        </li>
                    @empty
                        <li class="ix-pstep ix-pstep--empty"><p class="ix-hint" style="margin:0">This solution has no written steps yet.</p></li>
                    @endforelse
                </ol>
            </section>

            <livewire:solution-confirmations :solution-id="$solution->id" />

            <livewire:comments :model="$solution" :solution="$solution" />
        </article>

        <aside class="ix-detail__rail" aria-label="About this solution">
            <section class="ix-facts">
                <h2>Details</h2>
                <dl>
                    <div><dt>Written by</dt><dd><a href="{{ route('profile.show', ['id' => $solution->user->id]) }}">{{ $solution->user->name }}</a>@if ($solution->user->isOfficialAccount()) <span class="ix-chip ix-chip--info">Official</span>@endif</dd></div>
                    <div><dt>Added</dt><dd>{{ $solution->created_at->diffForHumans() }}</dd></div>
                    <div><dt>Time it takes</dt><dd>@if ($solution->duration_type === 'infinite') Unknown @else {{ $solution->duration }} {{ Str::plural(rtrim($solution->duration_type, 's'), $solution->duration) }} @endif</dd></div>
                    <div><dt>Visible to</dt><dd>{{ $solution->isHidden() ? 'You and moderators' : ($solution->isPublic() ? 'Everyone' : 'Your company') }}</dd></div>
                </dl>
            </section>

            @include('topics._rail', ['item' => $solution, 'choices' => $topicChoices, 'action' => route('solutions.topics', ['id' => $solution->id]), 'kind' => 'solution'])

            <livewire:verify-solution :solution-id="$solution->id" />

            @if ($related->isNotEmpty())
                <section class="ix-related">
                    <h2>More like this</h2>
                    <ul>
                        @foreach ($related as $other)
                            <li>
                                <a href="{{ route('solutions.view', ['id' => $other->id]) }}">{{ $other->solution_title }}</a>
                                <span class="ix-ref">{{ \App\Support\Reference::for($other) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <livewire:report-content kind="solution" :item-id="$solution->id" />

            <a href="{{ route('add') }}" class="ix-btn">Write another solution</a>
        </aside>
    </div>
</x-app-layout>
