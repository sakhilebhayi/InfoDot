<div class="ix-comment">
    <x-ix.avatar :user="$comment->user" square aria-hidden="true" />
    <div>
        <div class="ix-comment__by">
            {{ $comment->user->name ?? 'Former member' }}
            @if ($comment->commentable && $comment->user_id !== null && $comment->user_id === $comment->commentable->user_id)<em class="ix-comment__tag">{{ $comment->commentable instanceof \App\Models\Questions ? 'Asker' : 'Author' }}</em>@endif
            <span>{{ $comment->created_at->diffForHumans() }}</span>
        </div>
        <p>{!! nl2br(e($comment->body)) !!}</p>
    @include('partials.mention-chips', ['item' => $comment])
    @include('attachments._list', ['attachments' => $comment->attachments, 'compact' => true])
    </div>
</div>
