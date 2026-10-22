<div class="ix-mod">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Topics</h1>
            <p class="ix-lede">The short list people file questions and solutions under. Add a topic, rename one, or switch one off. Nothing is deleted, and every change is written to the permanent trail.</p>
        </div>
    </div>

    @if (session('topics-status'))
        <p class="ix-answers__status" role="status">{{ session('topics-status') }}</p>
    @endif

    @include('livewire.moderation._tabs', ['current' => 'topics'])

    <nav class="ix-mod__views" aria-label="Topic views">
        <a href="{{ route('moderation.topics', ['view' => 'list']) }}" @if ($tab === 'list') aria-current="page" @endif>The list</a>
        <a href="{{ route('moderation.topics', ['view' => 'untagged']) }}" @if ($tab === 'untagged') aria-current="page" @endif>Needs a topic <span class="ix-mod__n">{{ $untaggedQuestionCount }} {{ Str::plural('question', $untaggedQuestionCount) }}, {{ $untaggedSolutionCount }} {{ Str::plural('solution', $untaggedSolutionCount) }}</span></a>
    </nav>

    @if ($tab === 'list')
        <form wire:submit="add" class="ix-mod__form ix-topicadd" aria-labelledby="topic-add-h">
            <h2 id="topic-add-h" class="ix-mod__subh">Add a topic</h2>
            <label for="new-topic-name">Name</label>
            <input id="new-topic-name" type="text" class="ix-input" wire:model="newName" maxlength="{{ \App\Models\Topic::NAME_MAX }}" required autocomplete="off">
            @error('newName') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            <label for="new-topic-description">What belongs here (optional)</label>
            <input id="new-topic-description" type="text" class="ix-input" wire:model="newDescription" maxlength="{{ \App\Services\Knowledge\TopicService::DESCRIPTION_MAX }}" autocomplete="off">
            @error('newDescription') <p class="ix-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="ix-btn ix-btn--primary">Add topic</button>
        </form>

        <ol class="ix-mod__list ix-topiclist">
            @foreach ($topics as $topic)
                @php($q = (int) ($usage['questions'][$topic->id] ?? 0))
                @php($s = (int) ($usage['solutions'][$topic->id] ?? 0))
                <li class="ix-mod__item" wire:key="topic-{{ $topic->id }}">
                    <div class="ix-mod__head">
                        <span class="ix-chip {{ $topic->active ? 'ix-chip--done' : 'ix-chip--open' }}">{{ $topic->active ? 'On' : 'Switched off' }}</span>
                        <span class="ix-ref">{{ $topic->slug }}</span>
                        <span class="ix-mod__age">{{ $q }} {{ Str::plural('question', $q) }}, {{ $s }} {{ Str::plural('solution', $s) }} filed here</span>
                    </div>
                    <h2 class="ix-mod__title"><a href="{{ $topic->url() }}">{{ $topic->name }}</a></h2>
                    @if (filled($topic->description))<p class="ix-mod__excerpt">{{ $topic->description }}</p>@endif

                    <div class="ix-mod__actions">
                        <details class="ix-mod__act" wire:ignore.self wire:key="rename-{{ $topic->id }}">
                            <summary class="ix-btn">Rename</summary>
                            <form wire:submit="save({{ $topic->id }})" class="ix-mod__form">
                                <label for="name-{{ $topic->id }}">Name</label>
                                <input id="name-{{ $topic->id }}" type="text" class="ix-input" wire:model="names.{{ $topic->id }}" maxlength="{{ \App\Models\Topic::NAME_MAX }}" autocomplete="off">
                                <label for="desc-{{ $topic->id }}">What belongs here</label>
                                <input id="desc-{{ $topic->id }}" type="text" class="ix-input" wire:model="descriptions.{{ $topic->id }}" maxlength="{{ \App\Services\Knowledge\TopicService::DESCRIPTION_MAX }}" autocomplete="off">
                                @error('names.'.$topic->id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                <p class="ix-hint">The address stays {{ '/topics/'.$topic->slug }}, so links to it keep working.</p>
                                <button type="submit" class="ix-btn ix-btn--primary">Save</button>
                            </form>
                        </details>
                        <button type="button" class="ix-btn {{ $topic->active ? 'ix-btn--danger' : '' }}" wire:click="toggle({{ $topic->id }})" wire:loading.attr="disabled">{{ $topic->active ? 'Switch off' : 'Switch on' }}<span class="ix-sr"> {{ $topic->name }}</span></button>
                        @error('toggle.'.$topic->id) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="ix-hint">Switching a topic off stops anyone choosing it for something new. What is already filed under it keeps its label. A topic is never deleted while anything uses it.</p>
    @else
        <nav class="ix-filters" role="group" aria-label="Kind" style="margin-bottom:12px">
            <button type="button" class="ix-pill" aria-pressed="{{ $kind === 'question' ? 'true' : 'false' }}" wire:click="$set('kind', 'question')">Questions <span class="ix-pill__n">{{ $untaggedQuestionCount }}</span></button>
            <button type="button" class="ix-pill" aria-pressed="{{ $kind === 'solution' ? 'true' : 'false' }}" wire:click="$set('kind', 'solution')">Solutions <span class="ix-pill__n">{{ $untaggedSolutionCount }}</span></button>
        </nav>
        <p class="ix-hint">Written before topics existed, so nothing has been guessed for them. Newest first. Your own work is not listed: another moderator files it. Work private to another company cannot be shown here.</p>

        @if ($rows->isEmpty())
            <p class="ix-mod__empty">{{ $kind === 'solution' ? 'Every solution you can see has a topic.' : 'Every question you can see has a topic.' }}</p>
        @else
            <ol class="ix-mod__list">
                @foreach ($rows as $item)
                    @php($key = ($kind === 'solution' ? 's' : 'q').'-'.$item->id)
                    <li class="ix-mod__item" wire:key="untagged-{{ $key }}">
                        <div class="ix-mod__head">
                            <span class="ix-chip ix-chip--info">{{ ucfirst($kind) }}</span>
                            @if ($item->isHidden())<span class="ix-chip ix-chip--open">Hidden</span>@endif
                            <span class="ix-chip">{{ $item->isPublic() ? 'Public' : 'Company only' }}</span>
                            <span class="ix-mod__age">{{ $kind === 'solution' ? 'added' : 'asked' }} {{ $item->created_at->diffForHumans() }}</span>
                        </div>
                        <h2 class="ix-mod__title"><a href="{{ $kind === 'solution' ? route('solutions.view', ['id' => $item->id]) : route('questions.view', ['qid' => $item->id]) }}">{{ Str::limit($kind === 'solution' ? $item->solution_title : $item->question, 160) }}</a></h2>
                        <p class="ix-mod__excerpt">{{ Str::limit($kind === 'solution' ? $item->solution_description : $item->description, 260) }}</p>
                        <p class="ix-mod__by">By {{ $item->user?->name ?? 'a former member' }}</p>

                        <div class="ix-mod__actions">
                            <details class="ix-mod__act" wire:ignore.self wire:key="file-{{ $key }}">
                                <summary class="ix-btn ix-btn--primary">Choose topics</summary>
                                <form wire:submit="assign('{{ $key }}')" class="ix-mod__form">
                                    @include('topics._picker', ['choices' => $choices, 'selected' => [], 'idPrefix' => 'pick-'.$key, 'live' => 'picks.'.$key, 'legend' => 'Topics'])
                                    @error('picks.'.$key) <p class="ix-error" role="alert">{{ $message }}</p> @enderror
                                    <button type="submit" class="ix-btn ix-btn--primary">File it</button>
                                </form>
                            </details>
                        </div>
                    </li>
                @endforeach
            </ol>

            {{ $rows->links() }}
        @endif
    @endif
</div>
