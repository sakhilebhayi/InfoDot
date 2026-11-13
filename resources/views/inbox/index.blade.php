<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Inbox</h1>
            <p class="ix-lede">Your conversations with businesses on InfoDot. To start one, open a business’s page and choose “Message business”.</p>
        </div>
        <a href="{{ route('businesses.index') }}" class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">storefront</span>Find a business</a>
    </div>

    @if ($businesses->isNotEmpty())
        <section class="ix-panel" aria-labelledby="work-h">
            <div class="ix-panel__head"><h2 id="work-h">Business inboxes you answer for</h2></div>
            <ul class="ix-inboxlinks ix-panel__body">
                @foreach ($businesses as $row)
                    <li><a href="{{ route('business.inbox', $row['team']) }}"><b>{{ $row['team']->name }}</b><span>{{ $row['active'] }} {{ \Illuminate\Support\Str::plural('conversation', $row['active']) }} not yet resolved</span></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="mine-h" class="ix-vgap">
        <h2 id="mine-h" class="ix-dir__h2">Your enquiries</h2>
        @if ($enquiries->isEmpty())
            <div class="ix-empty">
                <h2>No enquiries yet</h2>
                <p>When you message a business, the conversation lives here, and the bell tells you when it answers.</p>
            </div>
        @else
            <ul class="ix-threads">
                @foreach ($enquiries as $e)
                    @php
                        $unread = $e->unreadForCustomer($reads[$e->id] ?? null);
                    @endphp
                    <li @class(['is-unread' => $unread])>
                        <span class="ix-biz__logo ix-threads__logo" aria-hidden="true">@if ($e->team?->logo)<img src="{{ route('attachments.show', $e->team->logo) }}" alt="" width="40" height="40">@else{{ mb_substr(trim((string) $e->team?->name), 0, 1) }}@endif</span>
                        <div class="ix-threads__main">
                            <a href="{{ route('inbox.show', $e) }}" class="ix-threads__subject">{{ $e->subject }}</a>
                            <span class="ix-threads__who">{{ $e->team?->name ?? 'A business no longer on InfoDot' }} <x-demo-mark :of="$e" /></span>
                        </div>
                        <span class="ix-threads__state">
                            @if ($unread)<span class="ix-chip ix-chip--info">New reply</span>@endif
                            <span class="ix-chip @if($e->status === \App\Models\Enquiry::AWAITING) ix-chip--open @elseif(in_array($e->status, ['resolved', 'closed'], true)) ix-chip--done @endif">{{ \App\Models\Enquiry::CUSTOMER_STATUSES[$e->status] ?? $e->status }}</span>
                        </span>
                        <time class="ix-threads__when" datetime="{{ $e->updated_at->toIso8601String() }}">{{ $e->updated_at->diffForHumans() }}</time>
                    </li>
                @endforeach
            </ul>
            @include('partials.pager', ['pager' => $enquiries, 'label' => 'Enquiries'])
        @endif
    </section>
</x-app-layout>
