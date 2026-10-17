@php($isQuestion = ! empty($question))
@php($model = $isQuestion ? $question : $solution)
@php($liked = $model->likes()->where('user_id', Auth::id())->exists())
<div>
    <div class="ix-actions" role="group" aria-label="Reactions">
        <button type="button" class="ix-toggle" aria-pressed="{{ $liked ? 'true' : 'false' }}" wire:click="storeLike">
            <span class="material-symbols-rounded" aria-hidden="true">thumb_up</span>
            {{ $liked ? 'Liked' : 'Like' }}
            <x-ix.figure :value="$model->likes()->count()" />
        </button>

        @if($isQuestion)
            @if((int) $model->status === 1)
                <span class="ix-state ix-state--done">Solved</span>
            @elseif(Auth::id() === $model->user_id)
                <button type="button" class="ix-toggle" wire:click="markedAsSolved" wire:confirm="Mark this question as solved?">
                    <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>Mark as solved
                </button>
            @endif
        @endif
    </div>

    <h2 class="ix-h1" style="font-size:18px;margin:28px 0 4px">Replies <x-ix.figure :value="$comments->count()" /></h2>

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
