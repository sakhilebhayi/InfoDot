<x-app-layout>
    @php($editing = $seminar !== null)
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $editing ? 'Edit your seminar' : 'Host a seminar' }}</h1>
            <p class="ix-lede">Seminars are free. InfoDot does not run video: you bring your own meeting room (any video call or webinar link) and only people who registered get the link, from {{ config('seminars.join_opens_minutes_before', 15) }} minutes before the start.</p>
        </div>
    </div>

    <form class="ix-form" method="POST" action="{{ $editing ? route('seminars.update', $seminar) : route('seminars.store') }}">
        @csrf
        @if ($editing)@method('PUT')@endif

        @foreach ([['title', 'Title', 'text', 140, 'What will people learn?'], ['summary', 'One-line summary', 'text', 280, 'Shown in the list.']] as [$name, $label, $type, $max, $hint])
            <div class="ix-field">
                <label class="ix-label" for="{{ $name }}">{{ $label }}</label>
                <input class="ix-input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ old($name, $seminar?->$name) }}" required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                <p class="ix-hint">{{ $hint }}</p>
                @error($name)<p class="ix-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="ix-field">
            <label class="ix-label" for="description">Details (optional)</label>
            <textarea class="ix-textarea" id="description" name="description" rows="6" maxlength="5000">{{ old('description', $seminar?->description) }}</textarea>
            @error('description')<p class="ix-error">{{ $message }}</p>@enderror
        </div>

        <div class="ix-field">
            <label class="ix-label" for="topic_id">Topic (optional)</label>
            <select class="ix-input ix-select" id="topic_id" name="topic_id">
                <option value="">No topic</option>
                @foreach ($topics as $t)
                    <option value="{{ $t->id }}" @selected((int) old('topic_id', $seminar?->topic_id) === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="ix-form__row">
            <div class="ix-field">
                <label class="ix-label" for="starts_at">Starts ({{ config('app.timezone') }})</label>
                <input class="ix-input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $seminar?->starts_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" required>
                @error('starts_at')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <div class="ix-field">
                <label class="ix-label" for="duration_minutes">Length in minutes</label>
                <input class="ix-input" id="duration_minutes" name="duration_minutes" type="number" min="{{ config('seminars.min_duration_minutes') }}" max="{{ config('seminars.max_duration_minutes') }}" value="{{ old('duration_minutes', $seminar?->duration_minutes ?? 60) }}" required>
                @error('duration_minutes')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <div class="ix-field">
                <label class="ix-label" for="capacity">Places (optional)</label>
                <input class="ix-input" id="capacity" name="capacity" type="number" min="1" max="{{ config('seminars.max_capacity') }}" value="{{ old('capacity', $seminar?->capacity) }}">
                <p class="ix-hint">Leave empty for no limit. Extra people join a waiting list.</p>
                @error('capacity')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="ix-field">
            <label class="ix-label" for="join_url">Your meeting link (https)</label>
            <input class="ix-input" id="join_url" name="join_url" type="url" maxlength="500" value="{{ old('join_url', $seminar?->join_url) }}" placeholder="https://" required>
            <p class="ix-hint">Only people who registered are given this link, and only shortly before the start.</p>
            @error('join_url')<p class="ix-error">{{ $message }}</p>@enderror
        </div>

        <div class="ix-actions">
            <button class="ix-btn ix-btn--primary" type="submit" name="publish" value="1">{{ $seminar?->isPublished() ? 'Save' : 'Publish' }}</button>
            @if (! $seminar?->isPublished())<button class="ix-btn" type="submit">Save as draft</button>@endif
            <a class="ix-btn ix-btn--ghost" href="{{ $editing ? route('seminars.show', $seminar) : route('seminars') }}">Cancel</a>
        </div>
    </form>
</x-app-layout>
