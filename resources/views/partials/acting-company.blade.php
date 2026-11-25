{{-- R-66: "Booking for" / "Sending for". Shown only to someone who belongs to at least one company; the choice is checked on the server. --}}
@if ($companies->isNotEmpty())
    <div class="ix-form__row">
        <label class="ix-label" for="for_company">{{ $label }}</label>
        <select class="ix-select" id="for_company" name="for_company" aria-describedby="for_company-hint">
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((string) old('for_company', $selected) === (string) $company->id)>{{ $company->name }}</option>
            @endforeach
            <option value="me" @selected(old('for_company', $selected) === 'me' || ($selected === null && old('for_company') === null))>Just me</option>
        </select>
        <p class="ix-hint" id="for_company-hint">{{ $hint }}</p>
        @error('for_company')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
    </div>
@endif
