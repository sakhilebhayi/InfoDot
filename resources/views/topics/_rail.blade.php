{{-- Topics in the side rail of a question or solution. A moderator who may correct it also gets the form. --}}
<section class="ix-facts ix-topicsec" id="topics" aria-labelledby="topics-h">
    <h2 id="topics-h">Topics</h2>
    @include('topics._chips', ['item' => $item])

    @if (session('topics_notice'))
        <p class="ix-hint" role="status">{{ session('topics_notice') }}</p>
    @endif

    @if ($choices)
        <details class="ix-mod__act ix-topicsec__fix" @if ($errors->has('topics') || $errors->has('note')) open @endif>
            <summary class="ix-btn ix-btn--sm">Correct topics</summary>
            <form method="POST" action="{{ $action }}" class="ix-mod__form">
                @csrf
                @include('topics._picker', ['choices' => $choices, 'selected' => old('topics', $item->topics->pluck('id')->all()), 'idPrefix' => 'fix', 'legend' => 'Topics for this '.$kind])
                @error('topics')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
                <label for="fix-note">Note (optional)</label>
                <textarea id="fix-note" name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
                @error('note')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="ix-btn ix-btn--primary">Save topics</button>
                <p class="ix-hint">Recorded in the moderation trail.</p>
            </form>
        </details>
    @endif
</section>
