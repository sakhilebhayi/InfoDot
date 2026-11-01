<x-app-layout :title="$enquiry->subject">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $enquiry->subject }}</h1>
            <p class="ix-lede">From {{ $enquiry->customer?->name ?? 'a former InfoDot user' }}. {{ $enquiry->categoryLabel() }}, received {{ $enquiry->created_at->format('j M Y, H:i') }}{{ $enquiry->first_response_at ? '; first answered '.$enquiry->created_at->diffForHumans($enquiry->first_response_at, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]).' later' : '' }}.</p>
        </div>
        <a href="{{ route('business.inbox', $team) }}" class="ix-btn ix-btn--ghost">Back to the inbox</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    <div class="ix-convo">
        <div class="ix-convo__main">
            @include('inbox._thread', ['side' => 'business'])

            <form method="post" action="{{ route('business.inbox.reply', [$team, $enquiry]) }}" enctype="multipart/form-data" class="ix-form ix-compose ix-vgap">
                @csrf
                <div class="ix-form__row"><label class="ix-label" for="body">Write</label><textarea class="ix-input" id="body" name="body" rows="5" maxlength="{{ config('enquiries.max_length') }}" required>{{ old('body') }}</textarea></div>
                <div class="ix-form__row"><label class="ix-label" for="files">Files <span class="ix-hint">optional, up to {{ config('enquiries.max_files') }}</span></label><input class="ix-input" type="file" id="files" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4"></div>
                <div class="ix-compose__send">
                    <button type="submit" class="ix-btn ix-btn--primary" @if(! $enquiry->customer) disabled @endif><span class="material-symbols-rounded" aria-hidden="true">send</span>Reply to {{ $enquiry->customer?->name ?? 'the customer' }}</button>
                    <button type="submit" name="internal" value="1" class="ix-btn"><span class="material-symbols-rounded" aria-hidden="true">sticky_note_2</span>Add internal note</button>
                </div>
                <p class="ix-hint">Replies go out as {{ $team->name }}; the customer does not see which of you wrote them. Internal notes stay inside the business.</p>
            </form>
        </div>

        <aside class="ix-convo__rail" aria-label="Handling">
            <section class="ix-biz__card">
                <h2>Handling</h2>
                <form method="post" action="{{ route('business.inbox.update', [$team, $enquiry]) }}" class="ix-form">
                    @csrf
                    <div class="ix-field">
                        <label class="ix-label" for="status">Status</label>
                        <select class="ix-input" id="status" name="status">
                            @foreach (\App\Models\Enquiry::STATUSES as $value => $label)<option value="{{ $value }}" @selected($enquiry->status === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    @if ($manager)
                        <div class="ix-field">
                            <label class="ix-label" for="assigned_to">With</label>
                            <select class="ix-input" id="assigned_to" name="assigned_to">
                                <option value="">The whole inbox</option>
                                @foreach ($members as $member)<option value="{{ $member->id }}" @selected($enquiry->assigned_to === $member->id)>{{ $member->name }}</option>@endforeach
                            </select>
                        </div>
                    @else
                        <p class="ix-hint">With you. The owner or an administrator can give it to someone else.</p>
                    @endif
                    <div class="ix-field">
                        <label class="ix-label" for="follow_up_on">Follow up on</label>
                        <input class="ix-input" type="date" id="follow_up_on" name="follow_up_on" min="{{ now()->toDateString() }}" value="{{ $enquiry->follow_up_on?->toDateString() }}">
                        @if ($enquiry->follow_up_on)<label class="ix-choice__chk"><input type="checkbox" name="clear_follow_up" value="1"> Clear the date</label>@endif
                        <p class="ix-hint">On that morning, whoever has it gets a reminder.</p>
                    </div>
                    <button type="submit" class="ix-btn ix-btn--primary">Save</button>
                </form>
            </section>

            @if ($manager && $enquiry->customer)
                <section class="ix-biz__card">
                    <h2>Sender</h2>
                    @if ($blocked)
                        <p class="ix-trust__line">{{ $enquiry->customer->name }} cannot open new enquiries with {{ $team->name }}.</p>
                        <form method="post" action="{{ route('business.inbox.block', [$team, $enquiry]) }}">@csrf<input type="hidden" name="unblock" value="1"><button type="submit" class="ix-btn ix-btn--sm">Allow new enquiries again</button></form>
                    @else
                        <p class="ix-trust__line">If this person is sending spam or abuse, stop them opening new enquiries with {{ $team->name }}. They are not told who did it, and this conversation stays.</p>
                        <details><summary class="ix-btn ix-btn--danger ix-btn--sm">Block new enquiries</summary>
                            <form method="post" action="{{ route('business.inbox.block', [$team, $enquiry]) }}" class="ix-vgap">@csrf<button type="submit" class="ix-btn ix-btn--danger ix-btn--sm">Yes, block {{ $enquiry->customer->name }}</button></form>
                        </details>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
