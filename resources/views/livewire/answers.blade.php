{{-- The button that was pressed is replaced (and the answer moves), so focus goes to the answer itself. --}}
<section class="ix-answers" aria-labelledby="answers-heading"
         x-data @answer-changed.window="$nextTick(() => document.getElementById('answer-' + $event.detail.id)?.focus())">
    <h2 id="answers-heading" class="ix-answers__title">
        {{ $answers->count() === 1 ? '1 answer' : $answers->count().' answers' }}
    </h2>

    @if (session('answer-status'))
        <p class="ix-answers__status" role="status">{{ session('answer-status') }}</p>
    @endif

    @forelse ($answers as $answer)
        <article class="ix-answer {{ $answer->isAccepted() ? 'is-accepted' : '' }} {{ $answer->isHidden() ? 'is-hidden' : '' }}" id="answer-{{ $answer->id }}" tabindex="-1">
            @if ($answer->isHidden())
                <livewire:hidden-notice kind="answer" :item-id="$answer->id" :key="'hidden-'.$answer->id" />
            @endif

            @if ($answer->isAccepted())
                <p class="ix-answer__flag"><span class="material-symbols-rounded" aria-hidden="true">check_circle</span> Accepted as the solution</p>
            @endif

            <div class="ix-prose">{!! nl2br(e($answer->body)) !!}</div>

            @include('partials.mention-chips', ['item' => $answer])

            @include('attachments._list', ['attachments' => $answer->attachments, 'compact' => true])

            @foreach ($answer->recommendations as $recommendation)
                @continue($recommendation->solution === null || ! $service->canSeeContent($recommendation->solution, $user))
                <a class="ix-reccard" href="{{ route('solutions.view', ['id' => $recommendation->solution_id]) }}">
                    <span class="ix-reccard__kind"><span class="material-symbols-rounded" aria-hidden="true">lightbulb</span> Recommended solution</span>
                    <span class="ix-reccard__title">{{ $recommendation->solution->solution_title }}</span>
                    <span class="ix-reccard__meta">
                        {{ Str::limit((string) $recommendation->solution->solution_description, 110) }}
                        — by {{ $recommendation->solution->user->name ?? 'Former member' }}@if ($n = \App\Models\SolutionConfirmation::where('solution_id', $recommendation->solution_id)->count()), tried by {{ $n }}@endif
                    </span>
                </a>
            @endforeach

            <footer class="ix-answer__meta">
                <span>Answered by <a href="{{ route('profile.show', ['id' => $answer->user->id]) }}">{{ $answer->user->name }}</a>@if ($badge = $badges[$answer->user_id] ?? null) <span class="ix-chip" data-badge="{{ $badge['level'] }}" data-badge-topic="{{ $badge['slug'] }}" title="Other people accepted this person's answers in {{ $badge['topic'] }}">{{ $badge['topic'] }}: {{ $badge['level_name'] }}</span>@endif, {{ $answer->created_at->diffForHumans() }}</span>

                @if ($service->canAccept($answer, $user) && ! $answer->isAccepted())
                    <button type="button" class="ix-btn" wire:click="accept({{ $answer->id }})">Accept as the solution</button>
                @endif

                @if ($answer->isAccepted() && ($question->user_id === $user->id || $user->isModerator()))
                    <button type="button" class="ix-btn" wire:click="revoke({{ $answer->id }})">Remove acceptance</button>
                @endif

                @if ($answer->user_id !== $user->id && ! $answer->isHidden())
                    @if (in_array($answer->id, $reported, true))
                        <span>You reported this</span>
                    @else
                        <button type="button" class="ix-link" wire:click="startReport({{ $answer->id }})">Report</button>
                    @endif
                @endif
            </footer>

            @if ($reporting === $answer->id)
                <form wire:submit="report" class="ix-answer__report" x-init="$nextTick(() => $refs.reason.focus())">
                    <label for="reason-{{ $answer->id }}">What is wrong with it?</label>
                    <select id="reason-{{ $answer->id }}" x-ref="reason" wire:model="reason">
                        <option value="spam">Spam</option>
                        <option value="duplicate">Duplicate of another answer</option>
                        <option value="low_quality">Not a real answer</option>
                        <option value="harmful">Harmful or misleading</option>
                        <option value="other">Something else</option>
                    </select>
                    <button type="submit" class="ix-btn">Send report</button>
                    <button type="button" class="ix-link" wire:click="$set('reporting', null)">Cancel</button>
                    @error('report') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                </form>
            @endif
        </article>
    @empty
        <p class="ix-answers__empty">
            @if ($canAnswer)
                No one has answered yet. If you have done this before, tell the next person how.
            @else
                No one has answered yet, and a hidden question cannot be answered.
            @endif
        </p>
    @endforelse

    @if ($canAnswer)
        <form wire:submit="publish" class="ix-answerform">
            <label for="answer-body">Your answer</label>
            <x-mention-box payload-model="mentionsPayload" clear-event="answer-attachments-clear">
                <textarea id="answer-body" wire:model="body" rows="6" maxlength="10000"
                          placeholder="What did you do, what happened, and what would you do differently? Steps and numbers help."></textarea>
            </x-mention-box>
            @error('body') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            @error('mentions') <p class="ix-error" role="alert">{{ $message }}</p> @enderror

            <div class="ix-field" x-data="solutionPicker({ url: @js(route('solutions.picker')), max: 3, debounce: {{ (int) config('search.debounce_ms') }} })" x-on:answer-attachments-clear.window="clearAll()" @click.outside="open = false">
                <label class="ix-label" for="answer-recommend">Recommend an existing solution <span style="color:var(--ix-text-3);font-weight:400">(optional)</span></label>
                <input class="ix-input" id="answer-recommend" type="search" x-model="query" @input="onInput()" placeholder="Search published solutions by title or tag" autocomplete="off">
                <div class="ix-mention__drop" x-show="open" x-cloak role="listbox" aria-label="Solutions to recommend">
                    <template x-for="r in results" :key="r.id">
                        <button type="button" class="ix-mention__option" role="option" @mousedown.prevent="choose(r)">
                            <span x-text="r.name"></span>
                            <small x-text="r.ref + ' · ' + r.author + (r.tried ? ' · tried by ' + r.tried : '')"></small>
                        </button>
                    </template>
                    <p class="ix-hint" style="margin:6px 10px" x-show="!results.length && !searching">Nothing found for that.</p>
                </div>
                <p class="ix-error" x-show="note" x-text="note" x-cloak role="alert"></p>
                <ul class="ix-mention__chips" x-show="items.length" x-cloak aria-label="Solutions you will recommend">
                    <template x-for="it in items" :key="it.id">
                        <li class="ix-chip ix-chip--info">
                            <span x-text="it.name"></span>
                            <button type="button" class="ix-mention__x" @click="remove(it)" :aria-label="'Remove ' + it.name">&times;</button>
                        </li>
                    </template>
                </ul>
                <input type="hidden" x-ref="payload" wire:model="recommendationsPayload">
            </div>
            @error('recommendations') <p class="ix-error" role="alert">{{ $message }}</p> @enderror

            @include('attachments._uploader', ['idPrefix' => 'answer', 'payloadModel' => 'attachmentsPayload', 'clearEvent' => 'answer-attachments-clear', 'label' => 'Show your answer'])
            @error('attachments') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="ix-btn ix-btn--primary">Post your answer</button>
        </form>
    @endif
</section>
