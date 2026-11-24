<fieldset class="ix-choice">
    <legend class="ix-label">What do you want the business to do better? (optional, up to {{ $maxObjectives }})</legend>
    @foreach ($objectives as $key => $objective)
        <label class="ix-choice__opt">
            <input type="checkbox" name="objectives[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
            <span><b>{{ $objective['label'] }}</b></span>
        </label>
    @endforeach
    <p class="ix-hint">We use this only to suggest the platforms that fit. You can change it later.</p>
</fieldset>
