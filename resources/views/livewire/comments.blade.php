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

    <h2 class="ix-thread__head"><span class="ix-stencil-num">{{ str_pad((string) $comments->count(), 2, '0', STR_PAD_LEFT) }}</span> {{ Str::plural('reply', $comments->count()) }}</h2>

    <div class="ix-thread">
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
        @include('attachments._uploader', ['idPrefix' => 'comment', 'payloadModel' => 'attachmentsPayload', 'clearEvent' => 'comment-attachments-clear', 'label' => 'Add supporting files'])
        @error('attachments')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
        <div class="ix-actions" style="margin-top:12px">
            <button type="submit" class="ix-btn ix-btn--primary">Post reply</button>
        </div>
    </form>
</div>
