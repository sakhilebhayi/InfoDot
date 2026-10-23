<div class="ix-mod">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Verified library</h1>
            <p class="ix-lede">Dot.Brain suggests answers and solutions the community has already vouched for. You decide: nothing enters the library unless a moderator adopts it. Every decision is written to the permanent trail.</p>
        </div>
        <a href="{{ route('library') }}" class="ix-btn">See the library</a>
    </div>

    @if (session('moderation-status'))
        <p class="ix-answers__status" role="status">{{ session('moderation-status') }}</p>
    @endif

    @include('livewire.moderation._tabs', ['current' => 'library'])

    <section class="ix-lib__sec" aria-labelledby="lib-rec-h">
        <h2 id="lib-rec-h" class="ix-mod__subh">Recommended by Dot.Brain</h2>

        @if ($unavailable !== null)
            <div class="ix-empty ix-lib__state" role="status">
                <h2>Recommendations are unavailable right now</h2>
                <p>
                    @if ($unavailable === 'not_configured')
                        Dot.Memory is not connected to this library yet, so there is nothing to show. You can still add an answer or a solution by its reference below.
                    @elseif ($unavailable === 'rejected')
                        Dot.Memory did not accept this request, so the list cannot be shown. This is a set-up problem for whoever runs the platform, not something you did.
                    @else
                        Dot.Memory could not be reached, or did not answer properly. Nothing is being guessed in its place. Try again in a minute; your decisions are never lost.
                    @endif
                </p>
            </div>
        @elseif ($recommendations === [])
            <p class="ix-mod__empty">Dot.Brain has no open recommendations for the library right now.@if ($cursor !== '') <button type="button" class="ix-linkbtn" wire:click="firstRecommendations">Back to the first page</button>@endif</p>
        @else
            <p class="ix-hint">Oldest first, as Dot.Brain returned them. Read the item and the reasons, then adopt or decline. A recommendation is advice, not a decision.</p>
            <ol class="ix-mod__list">
                @foreach ($recommendations as $rec)
                    @php($id = $rec['decision_event_id'])
                    @php($c = $rec['content'])
                    <li class="ix-mod__item" wire:key="rec-{{ $id }}">
                        @if ($c['gone'])
                            <p class="ix-mod__excerpt">This item no longer exists, so it cannot be adopted.</p>
                        @else
                            <div class="ix-mod__head">
                                <span class="ix-chip ix-chip--info">{{ ucfirst($c['kind']) }}</span>
                                @if ($c['hidden'])<span class="ix-chip ix-chip--open">Hidden</span>@endif
                                @if ($rec['confidence'] !== null)
                                    <span class="ix-mod__count">Confidence {{ is_numeric($rec['confidence']) && $rec['confidence'] >= 0 && $rec['confidence'] <= 1 ? round($rec['confidence'] * 100).'%' : $rec['confidence'] }}</span>
                                @endif
                                @if (filled($rec['decided_at']))<span class="ix-mod__age">suggested {{ \Illuminate\Support\Carbon::parse($rec['decided_at'])->diffForHumans() }}</span>@endif
                            </div>

                            @if (! $c['public'])
                                <p class="ix-mod__excerpt">This belongs to a company and is private to it, so it cannot be shown here or adopted.</p>
                            @else
                                <h3 class="ix-mod__title"><a href="{{ $c['url'] }}">{{ $c['title'] }}</a></h3>
                                @if (filled($c['excerpt']))<p class="ix-mod__excerpt">{{ $c['excerpt'] }}</p>@endif
                                <dl class="ix-mod__facts">
                                    <div><dt>Topics</dt><dd>{{ $c['topics'] === [] ? 'Not filed under a topic yet' : implode(', ', $c['topics']) }}</dd></div>
                                    <div>
                                        <dt>Corroborated by</dt>
                                        <dd>
                                            @if ($c['corroboration'])
                                                {{ $c['corroboration']['label'] }} {{ $c['corroboration']['by']?->name ?? 'someone who has left' }}, {{ $c['corroboration']['at']->diffForHumans() }}
                                            @else
                                                No one other than its author
                                            @endif
                                        </dd>
                                    </div>
                                    <div><dt>Written by</dt><dd>{{ $c['author']?->name ?? 'a former member' }}</dd></div>
                                </dl>
                            @endif

                            <h4 class="ix-mod__sub">Why Dot.Brain suggests it</h4>
                            @if ($rec['rationale'] === [])
                                <p class="ix-hint">Dot.Brain gave no reasons with this one.</p>
                            @else
                                <ul class="ix-lib__why">
                                    @foreach ($rec['rationale'] as $step)
                                        <li><strong>{{ $step['rule'] }}</strong>@if ($step['effect'] !== '') <span class="ix-lib__effect">{{ $step['effect'] }}</span>@endif @if ($step['detail'] !== '')<span class="ix-mod__detail">{{ $step['detail'] }}</span>@endif</li>
                                    @endforeach
                                </ul>
                                @if (filled($rec['rule_version']))<p class="ix-hint">Rules version {{ $rec['rule_version'] }}.</p>@endif
                            @endif

                            <div class="ix-mod__actions">
                                @if ($c['own'])
                                    <p class="ix-hint">This is your own content, so another moderator has to decide.</p>
                                @else
                                    @if ($c['problem'] === null)
                                        <button type="button" class="ix-btn ix-btn--primary" wire:click="adopt({{ \Illuminate\Support\Js::from($id) }})" wire:loading.attr="disabled">Adopt into library</button>
                                    @else
                                        <p class="ix-hint">It cannot be adopted now: {{ $c['problem'] }}</p>
                                    @endif
                                    <button type="button" class="ix-btn" wire:click="decline({{ \Illuminate\Support\Js::from($id) }})" wire:loading.attr="disabled">Decline</button>
                                @endif
                                @error('adopt.'.$id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                @error('decline.'.$id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if ($skipped > 0)<p class="ix-hint">{{ $skipped }} {{ Str::plural('item', $skipped) }} from Dot.Memory could not be understood and {{ $skipped === 1 ? 'was' : 'were' }} left out.</p>@endif
        @endif

        @if ($unavailable === null && ($next !== null || $cursor !== ''))
            <p class="ix-lib__more">
                @if ($cursor !== '')<button type="button" class="ix-btn" wire:click="firstRecommendations">First recommendations</button>@endif
                @if ($next !== null)<button type="button" class="ix-btn" wire:click="nextRecommendations({{ \Illuminate\Support\Js::from($next) }})">Next recommendations</button>@endif
            </p>
        @endif

        @if ($waiting > 0)
            <p class="ix-hint" role="status">{{ $waiting }} {{ Str::plural('decision', $waiting) }} {{ $waiting === 1 ? 'is' : 'are' }} saved here and still on the way to Dot.Memory. They are retried automatically.</p>
        @endif
    </section>

    <section class="ix-lib__sec" aria-labelledby="lib-in-h">
        <h2 id="lib-in-h" class="ix-mod__subh">In the library</h2>

        @if ($entries->isEmpty())
            <p class="ix-mod__empty">Nothing has been adopted yet.</p>
        @else
            <ol class="ix-mod__list">
                @foreach ($entries as $row)
                    @php($entry = $row['entry'])
                    @php($c = $row['content'])
                    <li class="ix-mod__item" wire:key="entry-{{ $entry->id }}">
                        <div class="ix-mod__head">
                            <span class="ix-chip ix-chip--done">In the library</span>
                            <span class="ix-chip ix-chip--info">{{ ucfirst($entry->kind) }}</span>
                            @unless ($row['shown'])<span class="ix-chip ix-chip--open">Not showing: hidden or no longer public</span>@endunless
                            <span class="ix-mod__age">adopted {{ $entry->adopted_at->diffForHumans() }}@if ($entry->adopter) by {{ $entry->adopter->name }}@endif</span>
                        </div>
                        @if ($c['gone'])
                            <p class="ix-mod__excerpt">The source no longer exists.</p>
                        @elseif (! $c['public'])
                            <p class="ix-mod__excerpt">The source is private to a company, so it is not shown.</p>
                        @else
                            <h3 class="ix-mod__title"><a href="{{ $c['url'] }}">{{ $c['title'] }}</a></h3>
                        @endif

                        <div class="ix-mod__actions">
                            @if (! empty($c['own']))
                                <p class="ix-hint">This is your own content, so another moderator has to withdraw it.</p>
                            @elseif (! $c['gone'])
                                <details class="ix-mod__act" wire:ignore.self wire:key="withdraw-{{ $entry->id }}">
                                    <summary class="ix-btn ix-btn--danger">Withdraw</summary>
                                    <form wire:submit="withdraw({{ $entry->id }})" class="ix-mod__form">
                                        <label for="withdraw-{{ $entry->id }}">Why is it withdrawn?</label>
                                        <textarea id="withdraw-{{ $entry->id }}" wire:model="withdrawReasons.{{ $entry->id }}" rows="3" maxlength="1000" required></textarea>
                                        <p class="ix-hint">It leaves the library and the points the adoption earned its author are taken back.</p>
                                        @error('withdrawReasons.'.$entry->id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                        <button type="submit" class="ix-btn ix-btn--danger">Withdraw it</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
            {{ $entries->links() }}
        @endif
    </section>

    <section class="ix-lib__sec" aria-labelledby="lib-add-h">
        <h2 id="lib-add-h" class="ix-mod__subh">Add one yourself</h2>
        <p class="ix-hint">For an answer or a solution Dot.Brain has not suggested. The same rules apply: it must be public, not hidden, and corroborated by someone other than its author, and it cannot be yours.</p>
        <form method="POST" action="{{ route('moderation.library.add') }}" class="ix-mod__form ix-lib__add">
            @csrf
            <label for="lib-ref">Reference</label>
            <input id="lib-ref" name="ref" type="text" class="ix-input" value="{{ old('ref') }}" placeholder="infodot:solution:7 or infodot:answer:12" maxlength="60" required autocomplete="off" spellcheck="false">
            @error('ref') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="ix-btn ix-btn--primary">Add to library</button>
        </form>
    </section>
</div>
