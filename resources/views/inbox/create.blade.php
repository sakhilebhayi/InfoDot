<x-app-layout :title="'Message '.$team->name">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Message {{ $team->name }}</h1>
            <p class="ix-lede">Your message goes to the business, not to one person, and the people who answer for {{ $team->name }} reply here on InfoDot. They see your name; your email address and phone number stay private.</p>
        </div>
        <a href="{{ route('business.show', $team) }}" class="ix-btn ix-btn--ghost">Back to the business</a>
    </div>

    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    @if ($reason)
        <div class="ix-empty">
            <h2>You cannot send a new enquiry here right now</h2>
            <p>{{ $reason }}</p>
            <a class="ix-btn" href="{{ route('inbox') }}">Go to your inbox</a>
        </div>
    @else
        <form method="post" action="{{ route('business.message.store', $team) }}" enctype="multipart/form-data" class="ix-form ix-compose">
            @csrf
            <div class="ix-form__row">
                <label class="ix-label" for="category">What it is about</label>
                <select class="ix-input" id="category" name="category" required>
                    @foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(old('category', 'general') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="ix-form__row"><label class="ix-label" for="subject">Subject</label><input class="ix-input" id="subject" name="subject" minlength="3" maxlength="160" required value="{{ old('subject') }}" placeholder="For example: Quote for a borehole in Hazyview"></div>
            <div class="ix-form__row">
                <label class="ix-label" for="body">Your message</label>
                <textarea class="ix-input" id="body" name="body" rows="8" minlength="{{ config('enquiries.min_length') }}" maxlength="{{ config('enquiries.max_length') }}" required placeholder="What you need, where, and by when. The more the business knows, the better it can answer.">{{ old('body') }}</textarea>
            </div>
            <div class="ix-form__row">
                <label class="ix-label" for="files">Files <span class="ix-hint">optional; up to {{ config('enquiries.max_files') }} images, PDFs or videos</span></label>
                <input class="ix-input" type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4">
            </div>
            <p class="ix-hint">Only {{ $team->name }}’s owner, its administrators and the person they give your enquiry to can read it. Please do not send passwords, card numbers or ID numbers.</p>
            <div><button type="submit" class="ix-btn ix-btn--primary"><span class="material-symbols-rounded" aria-hidden="true">send</span>Send to {{ $team->name }}</button></div>
        </form>
    @endif
</x-app-layout>
