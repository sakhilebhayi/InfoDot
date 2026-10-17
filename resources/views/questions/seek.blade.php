<x-app-layout>

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Ask a question</h1>
            <p class="ix-lede">One clear question gets a faster answer. Say what you tried and what happened.</p>
        </div>
    </div>

    <form class="ix-form" method="POST" action="{{ route('questions.add') }}" onkeydown="return event.key != 'Enter' || event.target.tagName === 'TEXTAREA';">
        @csrf
        <div class="ix-field">
            <label class="ix-label" for="question">Question</label>
            <input class="ix-input" name="question" id="question" type="text" value="{{ old('question') }}" placeholder="How do we rotate the shared mail password?" maxlength="255" required @error('question') aria-invalid="true" aria-describedby="question-error" @enderror>
            @error('question')<p class="ix-error" id="question-error">{{ $message }}</p>@enderror
        </div>

        <div class="ix-field">
            <label class="ix-label" for="description">Details</label>
            <textarea class="ix-textarea" name="description" id="description" rows="7" placeholder="What are you trying to do, what did you try, and what happened?" required @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>
            @error('description')<p class="ix-error" id="description-error">{{ $message }}</p>@enderror
        </div>

        <div class="ix-actions">
            <button class="ix-btn ix-btn--primary" type="submit">Post question</button>
            <a class="ix-btn ix-btn--ghost" href="{{ route('questions') }}">Cancel</a>
        </div>
    </form>
</x-app-layout>
