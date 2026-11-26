<x-app-layout>
    <header class="ix-inhead">
        <div>
            <h1 class="ix-h1">Inbox</h1>
            <p class="ix-lede">Your conversations with businesses on InfoDot. To start one, open a business’s page and choose “Message business”.</p>
        </div>
        <a href="{{ route('businesses.index') }}" class="ix-btn"><span class="material-symbols-rounded" aria-hidden="true">storefront</span>Find a business</a>
    </header>

    @if ($businesses->isNotEmpty())
        <section class="ix-inwork" aria-labelledby="work-h">
            <h2 id="work-h" class="ix-inbox__h2">Business inboxes you answer for</h2>
            <ul class="ix-inwork__list" role="list">
                @foreach ($businesses as $row)
                    <li><a href="{{ route('business.inbox', $row['team']) }}">
                        <span class="ix-inwork__name">{{ $row['team']->name }}</span>
                        <span class="ix-inwork__count"><b>{{ $row['active'] }}</b> {{ \Illuminate\Support\Str::plural('conversation', $row['active']) }} not yet resolved</span>
                        <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="ix-inbox" aria-labelledby="mine-h">
        <div class="ix-inbox__bar">
            <h2 id="mine-h" class="ix-inbox__h2">Your enquiries</h2>
            @if ($enquiries->total() > 0)<span class="ix-inbox__count">{{ $enquiries->total() }} {{ \Illuminate\Support\Str::plural('conversation', $enquiries->total()) }}</span>@endif
        </div>
        @if ($enquiries->isEmpty())
            <div class="ix-inbox__empty">
                <span class="material-symbols-rounded" aria-hidden="true">forum</span>
                <h3>No enquiries yet</h3>
                <p>When you message a business, the conversation lives here, and the bell tells you when it answers.</p>
            </div>
        @else
            <ul class="ix-inbox__rows" role="list">
                @foreach ($enquiries as $e)
                    @include('inbox._row', [
                        'href' => route('inbox.show', $e),
                        'subject' => $e->subject,
                        'who' => $e->team?->name ?? 'A business no longer on InfoDot',
                        'meta' => [$e->categoryLabel()],
                        'plate' => $e->team?->logo?->onDisk() ? route('attachments.show', $e->team->logo) : null,
                        'initial' => $e->team?->name,
                        'unread' => $e->unreadForCustomer($reads[$e->id] ?? null),
                        'unreadLabel' => 'New reply',
                        'status' => \App\Models\Enquiry::CUSTOMER_STATUSES[$e->status] ?? $e->status,
                        'statusTone' => $e->status === \App\Models\Enquiry::AWAITING ? 'open' : (in_array($e->status, ['resolved', 'closed'], true) ? 'done' : null),
                        'preview' => isset($latest[$e->id]) ? $latest[$e->id]->body : null,
                        'previewMine' => isset($latest[$e->id]) && $latest[$e->id]->kind === \App\Models\EnquiryMessage::CUSTOMER,
                        'updated' => $e->updated_at,
                        'of' => $e,
                    ])
                @endforeach
            </ul>
        @endif
    </section>
    @include('partials.pager', ['pager' => $enquiries, 'label' => 'Enquiries'])
</x-app-layout>
