<x-app-layout :title="$enquiry->subject">
    <header class="ix-convhead">
        <a href="{{ route('inbox') }}" class="ix-convhead__back"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>All enquiries</a>
        <div class="ix-convhead__title">
            <h1 class="ix-h1">{{ $enquiry->subject }}</h1>
            <span @class(['ix-chip', 'ix-chip--open' => $enquiry->status === \App\Models\Enquiry::AWAITING, 'ix-chip--done' => in_array($enquiry->status, ['resolved', 'closed'], true)])>{{ \App\Models\Enquiry::CUSTOMER_STATUSES[$enquiry->status] ?? $enquiry->status }}</span>
            <x-demo-mark :of="$enquiry" />
        </div>
        <dl class="ix-convhead__meta">
            <div><dt>With</dt><dd><a href="{{ route('business.show', $enquiry->team) }}">{{ $enquiry->team->name }}</a></dd></div>
            <div><dt>About</dt><dd>{{ $enquiry->categoryLabel() }}</dd></div>
            <div><dt>Opened</dt><dd>{{ $enquiry->created_at->format('j M Y') }}</dd></div>
        </dl>
    </header>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    <div class="ix-conv ix-conv--single">
        @include('inbox._appointment')

        @include('inbox._thread', ['side' => 'customer'])

        @if ($enquiry->isClosed())
            <p class="ix-conv__closed" role="note"><span class="material-symbols-rounded" aria-hidden="true">info</span><span>{{ $enquiry->team->name }} closed this enquiry. If you need more, <a href="{{ route('business.message', $enquiry->team) }}">send a new one</a>.</span></p>
        @else
            <form method="post" action="{{ route('inbox.reply', $enquiry) }}" enctype="multipart/form-data" class="ix-composer">
                @csrf
                <label class="ix-composer__label" for="body">Reply</label>
                <textarea class="ix-composer__text" id="body" name="body" rows="4" maxlength="{{ config('enquiries.max_length') }}" required placeholder="Write to {{ $enquiry->team->name }}">{{ old('body') }}</textarea>
                <div class="ix-composer__bar">
                    <label class="ix-composer__files"><span class="ix-sr">Files, optional, up to {{ config('enquiries.max_files') }}</span><input type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4"></label>
                    <div class="ix-composer__send">
                        <button type="submit" class="ix-btn ix-btn--primary"><span class="material-symbols-rounded" aria-hidden="true">send</span>Send</button>
                    </div>
                </div>
            </form>
            <p class="ix-composer__hint">{{ $enquiry->team->name }} reads this here on InfoDot. Files are optional: up to {{ config('enquiries.max_files') }} images, PDFs or videos.</p>
        @endif
    </div>
</x-app-layout>
