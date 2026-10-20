<div>
    @if ($show && $item->isHidden())
        <aside class="ix-hidden" aria-label="Hidden by a moderator">
            <p class="ix-hidden__title"><span class="material-symbols-rounded" aria-hidden="true">visibility_off</span> Hidden by a moderator</p>
            <p class="ix-hidden__reason">{{ $item->hidden_reason ?? 'No reason was recorded.' }}</p>
            <p class="ix-hidden__meta">
                {{ $item->hidden_at->format('j M Y') }}.
                @if ($isAuthor) Only you and moderators can see it now. @else Only its author and moderators can see it. @endif
            </p>

            @if ($isAuthor)
                @if ($appeal === null)
                    <form wire:submit="fileAppeal" class="ix-hidden__form">
                        <label for="appeal-{{ $kind }}-{{ $itemId }}">Appeal this decision</label>
                        <textarea id="appeal-{{ $kind }}-{{ $itemId }}" wire:model="appealText" rows="4" maxlength="1000" required
                                  placeholder="Tell the moderators what they missed."></textarea>
                        <p class="ix-hint">You can appeal once. A different moderator will decide and you will see the outcome here and on your profile.</p>
                        @error('appealText') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                        <button type="submit" class="ix-btn">Send appeal</button>
                    </form>
                @elseif ($appeal->isOpen())
                    <p class="ix-hidden__outcome"><strong>Your appeal is with the moderators.</strong> Sent {{ $appeal->created_at->diffForHumans() }}. A moderator who did not hide this will decide it.</p>
                @else
                    <p class="ix-hidden__outcome"><strong>Your appeal was {{ $appeal->status }}.</strong>
                        @if (filled($appeal->decision_note)) {{ $appeal->decision_note }} @endif
                        This decision is final.</p>
                @endif
            @endif

            @if ($canRestore)
                <button type="button" class="ix-btn" wire:click="restore">Restore it</button>
            @endif
        </aside>
    @elseif ($show && $restoredAppeal)
        <aside class="ix-hidden ix-hidden--ok" aria-label="Appeal outcome">
            <p class="ix-hidden__outcome"><strong>Your appeal was upheld.</strong> A moderator showed this again on {{ $restoredAppeal->decided_at?->format('j M Y') }}.
                @if (filled($restoredAppeal->decision_note)) {{ $restoredAppeal->decision_note }} @endif</p>
        </aside>
    @endif
</div>
