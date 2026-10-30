<div class="ix-comment">
    <span class="ix-avatar ix-avatar--sq" aria-hidden="true">{{ strtoupper(substr($comment->user->name ?? '?', 0, 1)) }}</span>
    <div>
        <div class="ix-comment__by">
            {{ $comment->user->name ?? 'Former member' }}
            @if ($comment->commentable && $comment->user_id !== null && $comment->user_id === $comment->commentable->user_id)<em class="ix-comment__tag">{{ $comment->commentable instanceof \App\Models\Questions ? 'Asker' : 'Author' }}</em>@endif
            <span>{{ $comment->created_at->diffForHumans() }}</span>
        </div>
        <p>{!! nl2br(e($comment->body)) !!}</p>
    @include('attachments._list', ['attachments' => $comment->attachments, 'compact' => true])
    </div>
</div>
