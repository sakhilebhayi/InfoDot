<div class="ix-mod">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Moderation</h1>
            <p class="ix-lede">Reports from members, and appeals from authors whose content was hidden. Every decision is written to a permanent trail.</p>
        </div>
    </div>

    @if (session('moderation-status'))
        <p class="ix-answers__status" role="status">{{ session('moderation-status') }}</p>
    @endif

    <nav class="ix-mod__tabs" aria-label="Moderation views">
        <a href="{{ route('moderation', ['tab' => 'reports']) }}" @if ($tab === 'reports') aria-current="page" @endif>Reports <span class="ix-mod__n">{{ $openReports }} open</span></a>
        <a href="{{ route('moderation', ['tab' => 'appeals']) }}" @if ($tab === 'appeals') aria-current="page" @endif>Appeals <span class="ix-mod__n">{{ $openAppeals }} open</span></a>
    </nav>

    <div class="ix-mod__filters">
        <label>
            <span>Show</span>
            <select wire:model.live="status" class="ix-input ix-select">
                @foreach ($statuses as $option)
                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </label>
        @if ($tab === 'reports')
            <label>
                <span>Reason</span>
                <select wire:model.live="reason" class="ix-input ix-select">
                    <option value="">Any reason</option>
                    @foreach (\App\Models\ContentReport::REASON_LABELS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    @if ($rows->isEmpty())
        <p class="ix-mod__empty">
            @if ($tab === 'reports')
                {{ $status === 'open' ? 'No open reports.' : 'No '.$status.' reports.' }}
            @else
                {{ $status === 'open' ? 'No appeals are waiting.' : 'No '.$status.' appeals.' }}
            @endif
        </p>
    @else
        <ol class="ix-mod__list">
            @foreach ($rows as $row)
                @php($item = $row['item'])
                <li class="ix-mod__item" wire:key="{{ $tab }}-{{ $loop->index }}-{{ $item['key'] ?? 'x' }}-{{ $row['appeal']->id ?? '' }}">
                    @if ($item['gone'])
                        <p class="ix-mod__excerpt">This item no longer exists.</p>
                    @elseif ($item['restricted'])
                        <p class="ix-mod__excerpt">This is private to another company, so it cannot be shown or decided from here.</p>
                    @else
                        <div class="ix-mod__head">
                            <span class="ix-chip ix-chip--info">{{ ucfirst($item['kind']) }}</span>
                            @if ($item['hidden'])<span class="ix-chip ix-chip--open">Hidden</span>@endif
                            @if (! empty($item['accepted']))<span class="ix-chip ix-chip--done">Accepted answer</span>@endif
                            @if (! empty($item['verified']))<span class="ix-chip ix-chip--done">Verified</span>@endif
                            @if ($tab === 'reports')
                                <span class="ix-mod__count">{{ $row['count'] }} {{ Str::plural('report', $row['count']) }}</span>
                                <span class="ix-mod__age">first {{ $row['since']->diffForHumans() }}</span>
                            @else
                                <span class="ix-mod__age">appealed {{ $row['appeal']->created_at->diffForHumans() }}</span>
                            @endif
                        </div>

                        <h2 class="ix-mod__title"><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h2>
                        @if (filled($item['excerpt']))<p class="ix-mod__excerpt">{{ $item['excerpt'] }}</p>@endif
                        <p class="ix-mod__by">By <a href="{{ route('profile.show', ['id' => $item['author']->id]) }}">{{ $item['author']->name }}</a></p>
                    @endif

                    @if ($tab === 'reports' && $row['reports']->isNotEmpty())
                        <h3 class="ix-mod__sub">{{ ucfirst($status) }} {{ Str::plural('report', $row['reports']->count()) }}</h3>
                        <ul class="ix-mod__reports">
                            @foreach ($row['reports'] as $report)
                                <li>
                                    <strong>{{ \App\Models\ContentReport::REASON_LABELS[$report->reason] ?? $report->reason }}</strong>
                                    by {{ $report->reporter?->name ?? 'a former member' }}, {{ $report->created_at->diffForHumans() }}
                                    @if (filled($report->detail))<span class="ix-mod__detail">{{ $report->detail }}</span>@endif
                                    @if ($report->status !== 'open')
                                        <span class="ix-mod__detail">{{ ucfirst($report->status) }} by {{ $report->resolver?->name ?? 'a moderator' }}{{ filled($report->resolution_note) ? ': '.$report->resolution_note : '' }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($tab === 'appeals')
                        @php($appeal = $row['appeal'])
                        <dl class="ix-mod__facts">
                            <div><dt>Hidden because</dt><dd>{{ $item['hiddenReason'] ?? 'The reason is on the item trail below.' }}</dd></div>
                            <div><dt>Hidden by</dt><dd>{{ $appeal->hiddenBy?->name ?? 'a moderator who has left' }}</dd></div>
                            <div><dt>The author says</dt><dd>{{ $appeal->body }}</dd></div>
                            @if (! $appeal->isOpen())
                                <div><dt>Decision</dt><dd>{{ ucfirst($appeal->status) }} by {{ $appeal->decider?->name ?? 'a moderator' }}, {{ $appeal->decided_at?->diffForHumans() }}@if (filled($appeal->decision_note)): {{ $appeal->decision_note }}@endif</dd></div>
                            @endif
                        </dl>
                    @endif

                    @unless ($item['gone'] || $item['restricted'])
                        <div class="ix-mod__actions">
                            @if ($tab === 'reports')
                                @if ($item['own'])
                                    <p class="ix-hint">This is your own content, so another moderator has to handle it.</p>
                                @elseif ($item['canModerate'])
                                    @if ($status === 'open')
                                        <details class="ix-mod__act">
                                            <summary class="ix-btn">Dismiss</summary>
                                            <form wire:submit="dismiss('{{ $item['key'] }}')" class="ix-mod__form">
                                                <label for="dismiss-{{ $item['key'] }}">Why are these reports dismissed?</label>
                                                <textarea id="dismiss-{{ $item['key'] }}" wire:model="dismissNotes.{{ $item['key'] }}" rows="3" maxlength="1000" required></textarea>
                                                @error('dismissNotes.'.$item['key']) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                                <button type="submit" class="ix-btn ix-btn--primary">Dismiss reports</button>
                                            </form>
                                        </details>
                                    @endif

                                    @if (! $item['hidden'] && $status !== 'dismissed')
                                        <details class="ix-mod__act">
                                            <summary class="ix-btn ix-btn--danger">Hide</summary>
                                            <form wire:submit="hide('{{ $item['key'] }}')" class="ix-mod__form">
                                                <label for="hide-{{ $item['key'] }}">Why is it hidden? The author will read this.</label>
                                                <textarea id="hide-{{ $item['key'] }}" wire:model="hideReasons.{{ $item['key'] }}" rows="3" maxlength="1000" required></textarea>
                                                @if (! empty($item['accepted']))
                                                    <p class="ix-hint">This is the accepted answer. Hiding it removes the acceptance and takes back the points it earned.</p>
                                                @endif
                                                @error('hideReasons.'.$item['key']) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                                <button type="submit" class="ix-btn ix-btn--danger">Hide it</button>
                                            </form>
                                        </details>
                                    @elseif ($item['hidden'])
                                        <details class="ix-mod__act">
                                            <summary class="ix-btn">Restore</summary>
                                            <form wire:submit="restore('{{ $item['key'] }}')" class="ix-mod__form">
                                                <label for="restore-{{ $item['key'] }}">Note (optional)</label>
                                                <textarea id="restore-{{ $item['key'] }}" wire:model="restoreNotes.{{ $item['key'] }}" rows="2" maxlength="1000"></textarea>
                                                @error('restoreNotes.'.$item['key']) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                                <button type="submit" class="ix-btn ix-btn--primary">Restore it</button>
                                            </form>
                                        </details>
                                    @endif
                                @endif
                            @elseif ($row['appeal']->isOpen())
                                @if ($row['canDecide'])
                                    <details class="ix-mod__act">
                                        <summary class="ix-btn">Uphold</summary>
                                        <form wire:submit="uphold({{ $row['appeal']->id }})" class="ix-mod__form">
                                            <label for="uphold-{{ $row['appeal']->id }}">Note (optional)</label>
                                            <textarea id="uphold-{{ $row['appeal']->id }}" wire:model="appealNotes.{{ $row['appeal']->id }}" rows="2" maxlength="1000"></textarea>
                                            @error('appealNotes.'.$row['appeal']->id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                            <button type="submit" class="ix-btn ix-btn--primary">Uphold and show it again</button>
                                        </form>
                                    </details>
                                    <details class="ix-mod__act">
                                        <summary class="ix-btn ix-btn--danger">Deny</summary>
                                        <form wire:submit="deny({{ $row['appeal']->id }})" class="ix-mod__form">
                                            <label for="deny-{{ $row['appeal']->id }}">Why is the appeal denied? The author will read this.</label>
                                            <textarea id="deny-{{ $row['appeal']->id }}" wire:model="appealNotes.{{ $row['appeal']->id }}" rows="3" maxlength="1000" required></textarea>
                                            @error('appealNotes.'.$row['appeal']->id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                            <button type="submit" class="ix-btn ix-btn--danger">Deny the appeal</button>
                                        </form>
                                    </details>
                                @else
                                    <p class="ix-hint">
                                        {{ $row['appeal']->hidden_by === auth()->id() ? 'You hid this, so a different moderator has to decide the appeal.' : 'This is your own content, so another moderator has to decide the appeal.' }}
                                    </p>
                                @endif
                            @endif
                        </div>

                        <details class="ix-mod__history">
                            <summary>History ({{ $item['history']->count() }})</summary>
                            @if ($item['history']->isEmpty())
                                <p class="ix-hint">No moderator has acted on this yet.</p>
                            @else
                                <ol>
                                    @foreach ($item['history'] as $entry)
                                        <li>
                                            <strong>{{ $entry->label() }}</strong>
                                            by {{ $entry->actor?->name ?? 'a moderator who has left' }}, {{ $entry->created_at->format('j M Y H:i') }}
                                            @if (filled($entry->reason))<span class="ix-mod__detail">{{ $entry->reason }}</span>@endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </details>
                    @endunless
                </li>
            @endforeach
        </ol>

        {{ $rows->links() }}
    @endif
</div>
