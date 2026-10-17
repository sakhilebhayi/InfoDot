<div class="ix-comment">
    <span class="ix-avatar" aria-hidden="true">{{ strtoupper(substr($comment->user->name ?? '?', 0, 1)) }}</span>
    <div>
        <div class="ix-comment__by">{{ $comment->user->name ?? 'Former member' }}<span>{{ $comment->created_at->diffForHumans() }}</span></div>
        <p>{{ $comment->body }}</p>
    </div>
</div>
