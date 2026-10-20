<div class="ix-report">
    @if (session('report-status'))
        <p class="ix-answers__status" role="status">{{ session('report-status') }}</p>
    @endif

    @if ($mine || $hidden)
        {{-- Nothing to report: your own content cannot be reported, and hidden content is already with the moderators. --}}
    @elseif ($reported)
        <p class="ix-hint">You have reported this. A moderator will look at it.</p>
    @elseif (! $open)
        <button type="button" class="ix-link" wire:click="$set('open', true)">Report this {{ $kind }}</button>
        @error('report') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
    @else
        <form wire:submit="send" class="ix-answer__report">
            <label for="report-reason-{{ $kind }}-{{ $itemId }}">What is wrong with it?</label>
            <select id="report-reason-{{ $kind }}-{{ $itemId }}" wire:model="reason">
                @foreach (\App\Models\ContentReport::REASON_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="ix-btn">Send report</button>
            <button type="button" class="ix-link" wire:click="$set('open', false)">Cancel</button>
            @error('report') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
        </form>
    @endif
</div>
