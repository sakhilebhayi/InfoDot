<x-app-layout>

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Add a solution</h1>
            <p class="ix-lede">Write a repeatable, step-by-step answer someone else on the team can follow without asking you.</p>
        </div>
    </div>

    <div class="ix-split" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr)">
        <form class="ix-form" style="max-width:none" method="POST" action="{{ route('solutions.add') }}"
              x-data="{ steps: {{ json_encode(collect(old('solution_heading', ['']))->map(fn ($h, $i) => ['h' => $h, 'b' => old('solution_body.'.$i, '')])->values()) }} }"
              onkeydown="return event.key != 'Enter' || event.target.tagName === 'TEXTAREA';">
            @csrf

            <div class="ix-field">
                <label class="ix-label" for="solution_title">Title</label>
                <input class="ix-input" name="solution_title" id="solution_title" type="text" value="{{ old('solution_title') }}" placeholder="{{ $industry['solution_title'] }}" maxlength="255" required @error('solution_title') aria-invalid="true" @enderror>
                @error('solution_title')<p class="ix-error">{{ $message }}</p>@enderror
            </div>

            <div class="ix-field">
                <label class="ix-label" for="solution_description">What it achieves</label>
                <textarea class="ix-textarea" name="solution_description" id="solution_description" rows="4" placeholder="{{ $industry['solution_description'] }}" required @error('solution_description') aria-invalid="true" @enderror>{{ old('solution_description') }}</textarea>
                @error('solution_description')<p class="ix-error">{{ $message }}</p>@enderror
            </div>

            <div class="ix-field">
                <label class="ix-label" for="tags-input">Tags</label>
                <input class="ix-input" name="tags-input" id="tags-input" type="text" value="{{ old('tags-input') }}" placeholder="{{ $industry['solution_tags'] }}" required @error('tags-input') aria-invalid="true" @enderror>
                <p class="ix-hint">Separate tags with commas. Specific tags are easier to find than broad ones.</p>
                @error('tags-input')<p class="ix-error">{{ $message }}</p>@enderror
            </div>

            <div class="ix-form__row">
                <div class="ix-field">
                    <label class="ix-label" for="duration">Time it takes</label>
                    <input class="ix-input" name="duration" id="duration" type="number" min="1" value="{{ old('duration') }}" placeholder="1" required @error('duration') aria-invalid="true" @enderror>
                    @error('duration')<p class="ix-error">{{ $message }}</p>@enderror
                </div>
                <div class="ix-field">
                    <label class="ix-label" for="duration_type">Unit</label>
                    <select class="ix-select ix-input" name="duration_type" id="duration_type">
                        @foreach (['hours' => 'Hours', 'days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years', 'infinite' => 'Unknown'] as $v => $label)
                            <option value="{{ $v }}" @selected(old('duration_type', 'hours') === $v)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('duration_type')<p class="ix-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <fieldset class="ix-panel" style="padding:0;margin:0;min-width:0">
                <legend class="ix-sr">Steps</legend>
                <div class="ix-panel__head"><span>Steps</span><span class="ix-fig" x-text="String(steps.length).padStart(2, '0')"></span></div>
                <template x-for="(step, i) in steps" :key="i">
                    <div class="ix-step">
                        <span class="ix-step__n ix-fig" x-text="String(i + 1).padStart(2, '0')"></span>
                        <div style="display:grid;gap:12px">
                            <div class="ix-field">
                                <label class="ix-label" :for="'heading-' + i">Heading</label>
                                <input class="ix-input" name="solution_heading[]" :id="'heading-' + i" type="text" x-model="step.h" maxlength="255" required>
                            </div>
                            <div class="ix-field">
                                <label class="ix-label" :for="'body-' + i">What to do</label>
                                <textarea class="ix-textarea" style="min-height:88px" name="solution_body[]" :id="'body-' + i" x-model="step.b" required></textarea>
                            </div>
                            <div>
                                <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" x-show="steps.length > 1" @click="steps.splice(i, 1)">
                                    <span class="material-symbols-rounded" aria-hidden="true" style="font-size:16px">delete</span>Remove step
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div style="padding:12px 16px;border-top:1px solid var(--ix-rule);background:var(--ix-sunken)">
                    <button type="button" class="ix-btn ix-btn--sm" @click="steps.push({ h: '', b: '' })">
                        <span class="material-symbols-rounded" aria-hidden="true" style="font-size:16px">add</span>Add step
                    </button>
                </div>
            </fieldset>
            @error('solution_heading')<p class="ix-error">{{ $message }}</p>@enderror
            @error('solution_heading.*')<p class="ix-error">{{ $message }}</p>@enderror
            @error('solution_body.*')<p class="ix-error">{{ $message }}</p>@enderror


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
                <button class="ix-btn ix-btn--primary" type="submit">Publish solution</button>
                <a class="ix-btn ix-btn--ghost" href="{{ route('solutions') }}">Cancel</a>
            </div>
        </form>

        <aside class="ix-panel" aria-label="Writing guide">
            <div class="ix-panel__head">Writing a solution people use</div>
            <div class="ix-panel__body" style="padding-bottom:0">
                <p class="ix-ideas__sub" style="margin:0 0 8px">Ideas for {{ $industry['label'] }}. Tap one to use it as the title.</p>
                <ul class="ix-ideas__list">
                    @foreach ($industry['solutions'] as $idea)
                        <li><button type="button" class="ix-idea" onclick="const f=document.getElementById('solution_title'); f.value=this.dataset.t; f.focus();" data-t="{{ $idea }}">{{ $idea }}</button></li>
                    @endforeach
                </ul>
            </div>
            <div class="ix-panel__body" style="display:grid;gap:16px;color:var(--ix-text-2)">
                <p style="margin:0"><b style="color:var(--ix-text)">Title.</b> Short and specific. &ldquo;Register a business on the CIPC website&rdquo; beats &ldquo;Start a business&rdquo;.</p>
                <p style="margin:0"><b style="color:var(--ix-text)">Description.</b> State the outcome and what is needed to get it, not a pitch.</p>
                <p style="margin:0"><b style="color:var(--ix-text)">Tags.</b> The words someone would type when they are stuck.</p>
                <p style="margin:0"><b style="color:var(--ix-text)">Time.</b> Realistic: &ldquo;1 week&rdquo; to register a business, not &ldquo;12 months&rdquo;.</p>
                <p style="margin:0"><b style="color:var(--ix-text)">Steps.</b> Each step leads straight into the next, with nothing missing in between. Keep them brief but fully understandable.</p>
            </div>
        </aside>
    </div>
</x-app-layout>
