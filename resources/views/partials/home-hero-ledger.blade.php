@if ($library->isNotEmpty())
    <div class="hx-ledger" data-hx-ledger>
        <p class="hx-ledger__head"><span class="pg-plate pg-plate--mustard" style="--pg-tone: var(--ix-platforms)">Library</span> <span>Researched, with named sources</span></p>
        <ol class="hx-ledger__list">
            @foreach ($library->take(3) as $entry)
                <li>
                    <a href="{{ route('solutions.view', $entry->id) }}">
                        <span class="ix-ref">{{ \App\Support\Reference::for($entry) }}</span>
                        <strong>{{ $entry->solution_title }}</strong>
                        <span class="hx-ledger__meta">{{ $entry->steps }} steps</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
@endif
