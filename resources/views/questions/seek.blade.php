<x-app-layout>

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Ask a question</h1>
            <p class="ix-lede">One clear question gets a faster answer. Say what you tried and what happened.</p>
        </div>
    </div>

    <div class="ix-ask-grid">
    <form class="ix-form" x-data="{ q: @js(old('question', request('question', ''))) }" method="POST" action="{{ route('questions.add') }}" onkeydown="return event.key != 'Enter' || event.target.tagName === 'TEXTAREA';">
        @csrf
        <div class="ix-field">
            <label class="ix-label" for="question">Question</label>
            <input class="ix-input" name="question" id="question" type="text" x-model="q" value="{{ old('question', request('question')) }}" placeholder="{{ $industry['ask_title'] }}" maxlength="255" required @error('question') aria-invalid="true" aria-describedby="question-error" @enderror>
            @error('question')<p class="ix-error" id="question-error">{{ $message }}</p>@enderror
        </div>

        <div class="ix-field">
            <label class="ix-label" for="description">Details</label>
            <x-mention-box name="mentions">
                <textarea class="ix-textarea" name="description" id="description" rows="7" placeholder="{{ $industry['ask_details'] }}" required @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
            </x-mention-box>
            <p class="ix-hint">What are you trying to achieve, what happened, since when, and what have you tried? Attach the evidence below — a report, photos of the problem, a short video.</p>
            @error('description')<p class="ix-error" id="description-error">{{ $message }}</p>@enderror
            @error('mentions')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
        </div>

        @include('partials.similar-panel', ['kind' => 'question', 'fields' => ['question', 'description']])

        @include('attachments._uploader', ['idPrefix' => 'ask', 'old' => old('attachments')])
        @error('attachments')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
        @error('attachments.*.uuid')<p class="ix-error" role="alert">{{ $message }}</p>@enderror


        <div class="ix-field">
            @include('topics._picker', ['choices' => $topicChoices, 'selected' => old('topics', $preselectedTopic ? [$preselectedTopic] : []), 'idPrefix' => 'ask-topic'])
            @error('topics')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
            @error('topics.*')<p class="ix-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="ix-field">
            <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                <input type="checkbox" name="publish" value="1" style="margin-top:3px" @checked(old('publish'))>
                <span>
                    <span class="ix-label" style="margin:0">Publish to the public community</span>
                    <span class="ix-hint" style="display:block;margin-top:2px">Off by default: only your company can see this. Turn it on to let every signed-in person, and signed-out visitors through search, read it. Do not publish anything confidential.</span>
                </span>
            </label>
        </div>

        <div class="ix-actions">
            <button class="ix-btn ix-btn--primary" type="submit">Post question</button>
            <a class="ix-btn ix-btn--ghost" href="{{ route('questions') }}">Cancel</a>
        </div>
    </form>

    <aside class="ix-ideas" aria-label="Question ideas">
        <h2 class="ix-ideas__head">Ideas for {{ $industry['label'] }}</h2>
        <p class="ix-ideas__sub">Tap one to start from it, then make it yours.</p>
        <ul>
            @foreach ($industry['questions'] as $idea)
                <li><button type="button" class="ix-idea" onclick="const f=document.getElementById('question'); f.value=this.dataset.q; f.dispatchEvent(new Event('input')); f.focus();" data-q="{{ $idea }}">{{ $idea }}</button></li>
            @endforeach
        </ul>
    </aside>
    </div>
</x-app-layout>
