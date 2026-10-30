@php($me = auth()->id())
<section class="ix-improve" aria-labelledby="improve-h">
    <div class="ix-improve__head">
        <h2 id="improve-h">Improvements <span class="ix-fig">v{{ str_pad((string) $solution->version, 2, '0', STR_PAD_LEFT) }}</span></h2>
        <button type="button" class="ix-btn ix-btn--sm" wire:click="$toggle('proposing')">
            <span class="material-symbols-rounded" aria-hidden="true">edit_note</span>Suggest an improvement
        </button>
    </div>
    <p class="ix-hint" style="margin:0">Found a simpler, cheaper, safer or more current way? Propose it. The author reviews every proposal; nothing changes until they approve, and approved changes credit you on the version history.</p>

    @if (session('improvement-status'))
        <p class="ix-pts__muted" role="status">{{ session('improvement-status') }}</p>
    @endif

    @if ($proposing)
        <form class="ix-improve__form" wire:submit="propose">
            <div class="ix-form__row">
                <div class="ix-field">
                    <label class="ix-label" for="imp-target">What are you improving?</label>
                    <select class="ix-select ix-input" id="imp-target" wire:model.live="form.target">
                        <option value="description">The description</option>
                        <option value="step">One of the steps</option>
                        <option value="new-step">Add a missing step</option>
                    </select>
                </div>
                @if ($form['target'] === 'step')
                    <div class="ix-field">
                        <label class="ix-label" for="imp-step">Which step</label>
                        <select class="ix-select ix-input" id="imp-step" wire:model="form.step_id">
                            <option value="">Pick a step</option>
                            @foreach ($steps as $i => $step)
                                <option value="{{ $step->id }}">{{ $i + 1 }}. {{ Str::limit($step->solution_heading, 40) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            @error('form.step_id')<p class="ix-error" role="alert">{{ $message }}</p>@enderror

            @if ($form['target'] !== 'description')
                <div class="ix-field">
                    <label class="ix-label" for="imp-heading">{{ $form['target'] === 'new-step' ? 'New step heading' : 'New heading (leave as is to keep the current one)' }}</label>
                    <input class="ix-input" id="imp-heading" type="text" maxlength="255" wire:model="form.heading">
                    @error('form.heading')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="ix-field">
                <label class="ix-label" for="imp-body">{{ $form['target'] === 'description' ? 'The improved description' : 'What to do (the improved text)' }}</label>
                <textarea class="ix-textarea" id="imp-body" rows="5" maxlength="5000" wire:model="form.body" placeholder="Write the replacement text in full, ready to use."></textarea>
                @error('form.body')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="ix-field">
                <label class="ix-label" for="imp-why">Why this is better</label>
                <textarea class="ix-textarea" id="imp-why" rows="3" maxlength="2000" wire:model="form.rationale" placeholder="Simpler, cheaper, safer, more current — and how you know (your experience, a source)."></textarea>
                @error('form.rationale')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
            </div>

            @include('attachments._uploader', ['idPrefix' => 'improve', 'payloadModel' => 'attachmentsPayload', 'clearEvent' => 'improvement-attachments-clear', 'label' => 'Supporting evidence'])
            @error('attachments')<p class="ix-error" role="alert">{{ $message }}</p>@enderror

            <div class="ix-actions" style="margin-top:4px">
                <button type="submit" class="ix-btn ix-btn--primary">Send to the author</button>
                <button type="button" class="ix-btn ix-btn--ghost" wire:click="$set('proposing', false)">Cancel</button>
            </div>
        </form>
    @endif

    @foreach ($proposals as $proposal)
        @php($isOpen = $proposal->isOpen())
        @if ($isOpen || $proposal->proposer_id === $me)
            <article class="ix-improve__card {{ $isOpen ? '' : 'is-closed' }}">
                <header class="ix-improve__meta">
                    <span class="ix-chip {{ $proposal->status === 'approved' ? 'ix-chip--done' : ($isOpen ? 'ix-chip--open' : '') }}">{{ ['pending' => 'Waiting for the author', 'clarify' => 'Clarification asked', 'approved' => 'Approved', 'rejected' => 'Not taken up'][$proposal->status] }}</span>
                    <span>{{ $proposal->proposer->name ?? 'Former member' }} · {{ $proposal->created_at->diffForHumans() }} ·
                        {{ $proposal->target_kind === 'description' ? 'the description' : ($proposal->target_kind === 'new-step' ? 'a new step' : 'step: '.Str::limit($proposal->step->solution_heading ?? '(removed)', 40)) }}</span>
                    @if ($proposal->isStale())<span class="ix-chip">Based on v{{ $proposal->base_version }} — the solution has moved on</span>@endif
                </header>

                <div class="ix-improve__compare">
                    <div>
                        <h3>Current</h3>
                        <p>{!! nl2br(e($proposal->target_kind === 'description' ? $solution->solution_description : ($proposal->target_kind === 'new-step' ? '(no such step yet)' : ($proposal->step->solution_body ?? '(step removed)')))) !!}</p>
                    </div>
                    <div>
                        <h3>Proposed{{ $proposal->proposed_heading ? ' — '.$proposal->proposed_heading : '' }}</h3>
                        <p>{!! nl2br(e($proposal->proposed_body)) !!}</p>
                    </div>
                </div>

                <p class="ix-improve__why"><b>Why:</b> {!! nl2br(e($proposal->rationale)) !!}</p>
                @include('attachments._list', ['attachments' => $proposal->attachments, 'compact' => true])

                @if ($proposal->decision_note)
                    <p class="ix-improve__why"><b>{{ $proposal->status === 'clarify' ? 'The author asks:' : 'Author’s note:' }}</b> {{ $proposal->decision_note }}</p>
                @endif

                @error('decision-'.$proposal->id)<p class="ix-error" role="alert">{{ $message }}</p>@enderror

                @if ($isAuthor && $isOpen)
                    <div class="ix-improve__decide">
                        <input class="ix-input" type="text" maxlength="2000" placeholder="Optional note to the proposer (required when asking for clarification)"
                               wire:model="notes.{{ $proposal->id }}" aria-label="Note to the proposer">
                        <div class="ix-actions" style="margin:0">
                            <button type="button" class="ix-btn ix-btn--sm ix-btn--primary" wire:click="approve({{ $proposal->id }})" wire:confirm="Apply this change and publish a new version crediting {{ $proposal->proposer->name ?? 'the proposer' }}?">Approve</button>
                            <button type="button" class="ix-btn ix-btn--sm" wire:click="clarify({{ $proposal->id }})">Ask to clarify</button>
                            <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" wire:click="reject({{ $proposal->id }})" wire:confirm="Keep the solution as it is and close this proposal?">Reject</button>
                        </div>
                    </div>
                @elseif ($proposal->proposer_id === $me && $isOpen)
                    @if ($revising === $proposal->id)
                        <form class="ix-improve__form" wire:submit="saveRevision">
                            <textarea class="ix-textarea" rows="4" maxlength="5000" wire:model="revision.body" aria-label="Revised proposed text"></textarea>
                            @error('revision.body')<p class="ix-error">{{ $message }}</p>@enderror
                            <textarea class="ix-textarea" rows="2" maxlength="2000" wire:model="revision.rationale" aria-label="Revised reason"></textarea>
                            @error('revision.rationale')<p class="ix-error">{{ $message }}</p>@enderror
                            <div class="ix-actions" style="margin:0">
                                <button type="submit" class="ix-btn ix-btn--sm ix-btn--primary">Send revision</button>
                                <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" wire:click="$set('revising', null)">Cancel</button>
                            </div>
                        </form>
                    @else
                        <div><button type="button" class="ix-btn ix-btn--sm" wire:click="startRevise({{ $proposal->id }})">Revise your proposal</button></div>
                    @endif
                @endif
            </article>
        @endif
    @endforeach

    @if ($versions->isNotEmpty())
        <details class="ix-improve__history">
            <summary>Version history ({{ $versions->count() }})</summary>
            <ol class="ix-improve__versions">
                @foreach ($versions as $version)
                    <li>
                        <details>
                            <summary>
                                <b>v{{ $version->version }}</b> — {{ $version->created_at?->format('j M Y') }}
                                @if ($version->contributor_id) · improved by {{ $version->contributor->name ?? 'Former member' }} @endif
                                @if ($version->summary) · {{ $version->summary }} @endif
                            </summary>
                            <div class="ix-improve__snapshot">
                                <p>{!! nl2br(e($version->solution_description)) !!}</p>
                                <ol>
                                    @foreach ($version->steps as $step)
                                        <li><b>{{ $step['heading'] }}</b><br>{!! nl2br(e($step['body'])) !!}</li>
                                    @endforeach
                                </ol>
                            </div>
                        </details>
                    </li>
                @endforeach
            </ol>
        </details>
    @endif
</section>
