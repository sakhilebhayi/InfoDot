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

            <div class="ix-prose">{{ $answer->body }}</div>

            <footer class="ix-answer__meta">
                <span>Answered by <a href="{{ route('profile.show', ['id' => $answer->user->id]) }}">{{ $answer->user->name }}</a>, {{ $answer->created_at->diffForHumans() }}</span>

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
            <textarea id="answer-body" wire:model="body" rows="6" maxlength="10000"
                      placeholder="What did you do, what happened, and what would you do differently? Steps and numbers help."></textarea>
            @error('body') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="ix-btn ix-btn--primary">Post your answer</button>
        </form>
    @endif
</section>
