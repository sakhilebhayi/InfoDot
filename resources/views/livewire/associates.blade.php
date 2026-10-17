@php($following = Auth::user()->associates()->where('associate_id', $user->id)->exists())
<div>
    <button type="button" wire:click="connect" class="ix-btn {{ $following ? '' : 'ix-btn--primary' }}">
        {{ $following ? 'Unfollow' : 'Follow' }}
    </button>
</div>
