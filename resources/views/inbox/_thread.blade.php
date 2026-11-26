{{-- One conversation. $side is 'customer' or 'business': the customer sees the business as the
     business (never which person answered) and never sees notes or events. Messages run in one
     column under a day line; who wrote each is the plate and the name, not a shifted bubble. --}}
<ol class="ix-thread ix-letters" aria-label="Conversation">
    @php $day = null; @endphp
    @foreach ($messages as $m)
        @php
            $mine = $side === 'customer' ? $m->kind === 'customer' : $m->kind === 'business';
            $who = match (true) {
                $m->kind === 'event' => null,
                $side === 'customer' && $m->kind === 'customer' => 'You',
                $side === 'customer' => $enquiry->team->name,
                $m->kind === 'customer' => $enquiry->customer?->name ?? 'Former InfoDot user',
                $m->kind === 'note' => $m->author?->name ?? 'A former member',
                default => $m->author?->name ?? 'A former member',
            };
            $role = match (true) {
                $m->kind === 'note' => 'Internal note',
                $side === 'business' && $m->kind === 'business' => 'for '.$enquiry->team->name,
                default => null,
            };
            $plate = match (true) {
                $side === 'customer' && $m->kind === 'business' => $enquiry->team->name,
                $side === 'customer' && $m->kind === 'customer' => $enquiry->customer?->name ?? $who,
                default => $who,
            };
            $thisDay = $m->created_at->toDateString();
        @endphp
        @if ($thisDay !== $day)
            @php $day = $thisDay; @endphp
            <li class="ix-thread__day" aria-hidden="true"><span>{{ $m->created_at->isToday() ? 'Today' : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('l j F Y')) }}</span></li>
        @endif
        @if ($m->kind === 'event')
            <li class="ix-thread__event" @if($loop->last) id="latest" @endif>
                <span class="ix-thread__tick" aria-hidden="true"></span>
                <span><span>{{ $m->body }}</span><span class="ix-hint">@if ($m->author) {{ $m->author->name }},@endif <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->format('j M, H:i') }}</time></span></span>
            </li>
        @else
            <li @class(['ix-msg', 'is-mine' => $mine, 'is-note' => $m->kind === 'note']) @if($loop->last) id="latest" @endif>
                <span class="ix-msg__plate" aria-hidden="true">@if ($m->kind === 'note')<span class="material-symbols-rounded">sticky_note_2</span>@else{{ mb_strtoupper(mb_substr(trim((string) $plate), 0, 1)) ?: '?' }}@endif</span>
                <div class="ix-msg__card">
                    <p class="ix-msg__head">
                        <b>{{ $who }}</b>@if ($role)<span class="ix-msg__role">{{ $role }}</span>@endif
                        <time datetime="{{ $m->created_at->toIso8601String() }}" title="{{ $m->created_at->format('j M Y, H:i') }}"><span class="ix-sr">{{ $m->created_at->format('j M Y,') }} </span>{{ $m->created_at->format('H:i') }}</time>
                    </p>
                    <div class="ix-msg__body">{{ $m->body }}</div>
                    @if ($files->has($m->id))
                        <ul class="ix-msg__files" role="list">
                            @foreach ($files->get($m->id) as $file)
                                <li><a href="{{ route('attachments.show', $file) }}" target="_blank" rel="noopener"><span class="material-symbols-rounded" aria-hidden="true">{{ $file->kind === 'image' ? 'image' : ($file->kind === 'pdf' ? 'picture_as_pdf' : 'movie') }}</span><span class="ix-msg__fname">{{ $file->name }}</span><span class="ix-hint">{{ $file->sizeLabel() }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($m->kind === 'note')<p class="ix-msg__private">Only people in {{ $enquiry->team->name }} see this.</p>@endif
                </div>
            </li>
        @endif
    @endforeach
</ol>
