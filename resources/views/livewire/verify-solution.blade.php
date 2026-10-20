<section class="ix-verify" aria-labelledby="verify-h-{{ $solution->id }}">
    <h2 id="verify-h-{{ $solution->id }}">Verification</h2>

    @if ($solution->isVerified())
        <p class="ix-verify__badge"><span class="material-symbols-rounded" aria-hidden="true">verified</span> Verified by moderator</p>
        <p class="ix-hint">{{ $solution->verifier?->name ?? 'A moderator' }} checked this on {{ $solution->verified_at->format('j M Y') }}.</p>
    @else
        <p class="ix-hint">No moderator has verified this solution yet.</p>
    @endif

    @if ($canModerate && ! $solution->isHidden())
        <form wire:submit="{{ $solution->isVerified() ? 'unverify' : 'verify' }}" class="ix-verify__form">
            <label for="verify-note-{{ $solution->id }}">{{ $solution->isVerified() ? 'Why is the verification removed?' : 'Note (optional)' }}</label>
            <textarea id="verify-note-{{ $solution->id }}" wire:model="note" rows="2" maxlength="1000" @if ($solution->isVerified()) required @endif></textarea>
            @error('note') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="ix-btn {{ $solution->isVerified() ? 'ix-btn--danger' : 'ix-btn--primary' }}">
                {{ $solution->isVerified() ? 'Remove verification' : 'Verify this solution' }}
            </button>
        </form>
    @endif
</section>
