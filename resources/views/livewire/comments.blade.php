@php($isQuestion = ! empty($question))
@php($model = $isQuestion ? $question : $solution)
<div>
    <div class="ix-actions">
        @if($isQuestion)
            @if((int) $model->status === 1)
                <span class="ix-stamp" role="status">Solved</span>
            @elseif(Auth::id() === $model->user_id)
                <button type="button" class="ix-toggle" wire:click="markedAsSolved" wire:confirm="Mark this question as solved?">
                    <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>Mark as solved
                </button>
            @endif
        @endif
    </div>

    <h2 class="ix-h1" style="font-size:16px;margin:24px 0 4px">Replies <span class="ix-hint" style="margin:0 0 0 6px">{{ $comments->count() }}</span></h2>

    <div>
        @forelse($comments as $comment)
            <livewire:comment :comment="$comment" :key="$comment->id" />
        @empty
            <p class="ix-hint" style="margin:8px 0 0">No replies yet. Be the first to answer.</p>
        @endforelse
    </div>

    <form wire:submit.prevent="postComment" style="margin-top:20px;max-width:680px">
        <label for="comment" class="ix-label">Your reply</label>
        <textarea name="comment" id="comment" rows="4" wire:model="newCommentState.body" class="ix-textarea" placeholder="Share what worked, or what you would try next."></textarea>
        @error('newCommentState.body')
            <p class="ix-error">{{ $message }}</p>
        @enderror
        <div class="ix-actions" style="margin-top:12px">
            <button type="submit" class="ix-btn ix-btn--primary">Post reply</button>
        </div>
    </form>
</div>
