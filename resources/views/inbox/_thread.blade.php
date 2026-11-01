{{-- One conversation. $side is 'customer' or 'business': the customer sees the business as the
     business (never which person answered) and never sees notes or events. --}}
<ol class="ix-thread" aria-label="Conversation">
    @foreach ($messages as $m)
        @php
            $mine = $side === 'customer' ? $m->kind === 'customer' : $m->kind === 'business';
            $who = match (true) {
                $m->kind === 'event' => null,
                $side === 'customer' && $m->kind === 'customer' => 'You',
                $side === 'customer' => $enquiry->team->name,
                $m->kind === 'customer' => $enquiry->customer?->name ?? 'Former InfoDot user',
                $m->kind === 'note' => 'Internal note by '.($m->author?->name ?? 'a former member'),
                default => ($m->author?->name ?? 'A former member').' for '.$enquiry->team->name,
            };
        @endphp
        @if ($m->kind === 'event')
            <li class="ix-thread__event" @if($loop->last) id="latest" @endif><span>{{ $m->body }}</span> <span class="ix-hint">{{ $m->author?->name }}, <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->format('j M, H:i') }}</time></span></li>
        @else
            <li @class(['ix-msg', 'is-mine' => $mine, 'is-note' => $m->kind === 'note']) @if($loop->last) id="latest" @endif>
                <p class="ix-msg__head"><b>{{ $who }}</b> <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->format('j M Y, H:i') }}</time></p>
                <div class="ix-msg__body">{{ $m->body }}</div>
                @if ($files->has($m->id))
                    <ul class="ix-msg__files">
                        @foreach ($files->get($m->id) as $file)
                            <li><a href="{{ route('attachments.show', $file) }}" target="_blank" rel="noopener"><span class="material-symbols-rounded" aria-hidden="true">{{ $file->kind === 'image' ? 'image' : ($file->kind === 'pdf' ? 'picture_as_pdf' : 'movie') }}</span>{{ $file->name }}</a> <span class="ix-hint">{{ $file->sizeLabel() }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endif
    @endforeach
</ol>
