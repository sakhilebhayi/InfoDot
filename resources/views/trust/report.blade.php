<x-app-layout :title="'Report '.$team->name">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Report {{ $team->name }}</h1>
            <p class="ix-lede">Tell InfoDot what is wrong. A reviewer looks at every report. A report alone changes nothing about the business — it is a reason to look, not a finding — and the business is never told who reported it.</p>
        </div>
        <a href="{{ route('business.show', $team) }}" class="ix-btn ix-btn--ghost">Back to the business</a>
    </div>

    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    @if ($reason)
        <div class="ix-empty"><h2>You cannot send a report here right now</h2><p>{{ $reason }}</p></div>
    @else
        <form method="post" action="{{ route('business.report.store', $team) }}" enctype="multipart/form-data" class="ix-form ix-compose">
            @csrf
            <fieldset class="ix-choice">
                <legend class="ix-label">What is the problem?</legend>
                @foreach ($categories as $value => $label)
                    <label class="ix-choice__opt"><input type="radio" name="category" value="{{ $value }}" required @checked(old('category') === $value)><span><b>{{ $label }}</b></span></label>
                @endforeach
            </fieldset>
            <div class="ix-form__row">
                <label class="ix-label" for="detail">What you saw</label>
                <textarea class="ix-textarea" id="detail" name="detail" rows="6" minlength="20" maxlength="2000" required placeholder="What is wrong, where on the page, and how you know. If another business is being copied, name it.">{{ old('detail') }}</textarea>
            </div>
            <div class="ix-form__row">
                <label class="ix-label" for="files">Supporting files <span class="ix-hint">optional; up to {{ \App\Services\Trust\BusinessTrustService::MAX_FILES }} screenshots or PDFs. Only reviewers see them.</span></label>
                <input class="ix-input" type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
            </div>
            <div><button type="submit" class="ix-btn ix-btn--primary">Send the report</button></div>
        </form>
    @endif
</x-app-layout>
