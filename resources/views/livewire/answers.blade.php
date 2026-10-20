<section class="ix-answers" aria-labelledby="answers-heading">
    <h2 id="answers-heading" class="ix-answers__title">
        {{ $answers->count() === 1 ? '1 answer' : $answers->count().' answers' }}
    </h2>

    @if (session('answer-status'))
        <p class="ix-answers__status" role="status">{{ session('answer-status') }}</p>
    @endif

    @forelse ($answers as $answer)
        <article class="ix-answer {{ $answer->isAccepted() ? 'is-accepted' : '' }}" id="answer-{{ $answer->id }}">
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

                @if ($answer->user_id !== $user->id)
                    <button type="button" class="ix-link" wire:click="startReport({{ $answer->id }})">Report</button>
                @endif
            </footer>

            @if ($reporting === $answer->id)
                <form wire:submit="report" class="ix-answer__report">
                    <label for="reason-{{ $answer->id }}">What is wrong with it?</label>
                    <select id="reason-{{ $answer->id }}" wire:model="reason">
                        <option value="spam">Spam</option>
                        <option value="duplicate">Duplicate of another answer</option>
                        <option value="low_quality">Not a real answer</option>
                        <option value="harmful">Harmful or misleading</option>
                        <option value="other">Something else</option>
                    </select>
                    <button type="submit" class="ix-btn">Send report</button>
                </form>
            @endif
        </article>
    @empty
        <p class="ix-answers__empty">No one has answered yet. If you have done this before, tell the next person how.</p>
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
