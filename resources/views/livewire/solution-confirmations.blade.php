<section class="ix-tried" aria-labelledby="tried-h">
    <div class="ix-tried__head">
        <h2 id="tried-h">Tried in practice</h2>
        <p class="ix-hint" style="margin:0">These are people saying they implemented this and it worked for them — community-reported, not independently verified, and no promise it works for every business.</p>
    </div>

    @if (session('confirmation-status'))
        <p class="ix-pts__muted" role="status">{{ session('confirmation-status') }}</p>
    @endif

    <div class="ix-tried__row">
        @unless ($isAuthor)
            <button type="button" class="ix-btn {{ $mine ? '' : 'ix-btn--primary' }}" wire:click="toggle" aria-pressed="{{ $mine ? 'true' : 'false' }}">
                <span class="material-symbols-rounded" aria-hidden="true">{{ $mine ? 'check_circle' : 'task_alt' }}</span>
                {{ $mine ? 'You tried this — undo' : 'I tried this and it works' }}
            </button>
        @endunless
        <p class="ix-tried__count" aria-live="polite">
            <span class="ix-stencil-num">{{ str_pad((string) min($confirmations->count(), 99), 2, '0', STR_PAD_LEFT) }}</span>
            {{ Str::plural('implementation confirmation', $confirmations->count()) }}
        </p>
    </div>
    @error('confirmation')<p class="ix-error" role="alert">{{ $message }}</p>@enderror

    @if ($mine && $showFeedback)
        <form class="ix-tried__feedback" wire:submit="saveFeedback">
            <p class="ix-hint" style="margin:0 0 8px">Optional: a line about your experience makes the confirmation far more useful to the next business. Skip anything you like.</p>
            <div class="ix-form__row">
                <div class="ix-field">
                    <label class="ix-label" for="fb-industry">Type of business</label>
                    <input class="ix-input" id="fb-industry" type="text" maxlength="100" wire:model="feedback.business_industry" placeholder="e.g. plumbing services, spaza shop">
                </div>
                <div class="ix-field">
                    <label class="ix-label" for="fb-timeframe">Time to see results</label>
                    <input class="ix-input" id="fb-timeframe" type="text" maxlength="100" wire:model="feedback.timeframe" placeholder="e.g. about 6 weeks">
                </div>
            </div>
            <div class="ix-field">
                <label class="ix-label" for="fb-part">What you implemented</label>
                <input class="ix-input" id="fb-part" type="text" maxlength="255" wire:model="feedback.applied_part" placeholder="The whole solution, or which steps">
            </div>
            <div class="ix-field">
                <label class="ix-label" for="fb-results">What happened</label>
                <textarea class="ix-textarea" id="fb-results" rows="2" maxlength="1000" wire:model="feedback.results" placeholder="The result you actually measured or noticed"></textarea>
            </div>
            <div class="ix-field">
                <label class="ix-label" for="fb-recommend">Would you recommend it?</label>
                <select class="ix-select ix-input" id="fb-recommend" wire:model="feedback.recommend">
                    <option value="">Rather not say</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="ix-actions" style="margin-top:4px">
                <button type="submit" class="ix-btn ix-btn--sm ix-btn--primary">Save my experience</button>
                <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" wire:click="$set('showFeedback', false)">Skip</button>
            </div>
        </form>
    @elseif ($mine)
        <p class="ix-hint" style="margin:0"><button type="button" class="ix-copy" wire:click="$set('showFeedback', true)">Add or edit your experience</button></p>
    @endif

    @if ($insights)
        <p class="ix-tried__insights">
            Tried by {{ $insights['businesses'] }} businesses@if ($insights['industries'] >= 2) across {{ $insights['industries'] }} industries@endif.
            @if ($insights['with_results'] > 0) {{ $insights['with_results'] }} reported their results.@endif
            @if ($insights['would_recommend']) {{ $insights['would_recommend'] }} who answered would recommend it.@endif
        </p>
    @endif

    @if ($confirmations->isNotEmpty())
        <ul class="ix-tried__people" aria-label="Who confirmed this works">
            @foreach ($confirmations->take(12) as $confirmation)
                <li>
                    <a href="{{ route('profile.show', ['id' => $confirmation->user_id]) }}">{{ $confirmation->user->name ?? 'Former member' }}</a>@if ($confirmation->business_industry) <span class="ix-hint">({{ $confirmation->business_industry }})</span>@endif
                </li>
            @endforeach
            @if ($confirmations->count() > 12)
                <li class="ix-hint">and {{ $confirmations->count() - 12 }} more</li>
            @endif
        </ul>
    @endif
</section>
