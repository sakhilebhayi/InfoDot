{{-- The resolved identities mentioned on an item (R-16). Include with $item (eager-load mentions). --}}
@php($rows = $item->relationLoaded('mentions') ? $item->mentions : $item->mentions()->with(['mentionedUser', 'mentionedTeam'])->get())
@if ($rows->isNotEmpty())
    <p class="ix-mentioned" aria-label="Mentioned here">
        <span class="ix-mentioned__label">Mentioned:</span>
        @foreach ($rows as $mention)
            @if ($mention->mentioned_user_id && $mention->mentionedUser)
                <x-ix.person class="ix-chip ix-chip--info" :user="$mention->mentionedUser" :name="'@'.$mention->mentionedUser->name" />
            @elseif ($mention->mentioned_team_id && $mention->mentionedTeam)
                <span class="ix-chip ix-chip--info" title="Business">{{ '@'.$mention->mentionedTeam->name }}</span>
            @endif
        @endforeach
    </p>
@endif
