<x-app-layout :title="$enquiry->subject">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $enquiry->subject }}</h1>
            <p class="ix-lede">With <a href="{{ route('business.show', $enquiry->team) }}">{{ $enquiry->team->name }}</a>. {{ $enquiry->categoryLabel() }}, opened {{ $enquiry->created_at->format('j M Y') }}.
                <span class="ix-chip">{{ \App\Models\Enquiry::CUSTOMER_STATUSES[$enquiry->status] ?? $enquiry->status }}</span></p>
        </div>
        <a href="{{ route('inbox') }}" class="ix-btn ix-btn--ghost">All enquiries</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    @include('inbox._thread', ['side' => 'customer'])

    @if ($enquiry->isClosed())
        <p class="ix-alert ix-vgap" role="note">{{ $enquiry->team->name }} closed this enquiry. If you need more, <a href="{{ route('business.message', $enquiry->team) }}">send a new one</a>.</p>
    @else
        <form method="post" action="{{ route('inbox.reply', $enquiry) }}" enctype="multipart/form-data" class="ix-form ix-compose ix-vgap">
            @csrf
            <div class="ix-form__row"><label class="ix-label" for="body">Reply</label><textarea class="ix-textarea" id="body" name="body" rows="4" maxlength="{{ config('enquiries.max_length') }}" required>{{ old('body') }}</textarea></div>
            <div class="ix-form__row"><label class="ix-label" for="files">Files <span class="ix-hint">optional, up to {{ config('enquiries.max_files') }}</span></label><input class="ix-input" type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4"></div>
            <div><button type="submit" class="ix-btn ix-btn--primary"><span class="material-symbols-rounded" aria-hidden="true">send</span>Send</button></div>
        </form>
    @endif
</x-app-layout>
