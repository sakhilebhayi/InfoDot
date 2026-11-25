<x-guest-layout :title="($another ?? false) ? 'Create another company' : 'Set up your company'">
    <x-jet-authentication-card>
        @if (request()->boolean('verified'))
            <p class="ix-alert" role="status">Your email address is confirmed.</p>
        @endif

        <h1 class="ix-auth-title">{{ ($another ?? false) ? 'Create another company' : 'Set up your company' }}</h1>
        <p class="ix-entry__lede">
            @if ($another ?? false)
                Each company has its own people, pages and platforms. Your login stays the same, and you can switch between companies from the menu.
            @elseif ($branding)
                {{ $branding->name }} is for businesses. Your account is ready; name your business to continue to {{ $branding->name }}.
            @else
                Your account is ready. Your company is what you manage on InfoDot: its page, its people and its platforms. A name and a sentence about what it does is enough to start. You can add the rest later.
            @endif
        </p>

        @if ($errors->any())
            <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="ix-entry__form" method="POST" action="{{ route('onboarding.business.store') }}">
            @csrf
            @if ($another ?? false)<input type="hidden" name="another" value="1">@endif

            <div class="ix-field">
                <x-jet-label for="name" value="Company name" />
                <x-jet-input id="name" type="text" name="name" :value="old('name')" required autofocus maxlength="255" autocomplete="organization" />
            </div>

            <div class="ix-field">
                <x-jet-label for="industry" value="Industry" />
                <select id="industry" name="industry" class="ix-input ix-select">
                    <option value="">{{ __('General business') }}</option>
                    @foreach ($industries as $key => $label)
                        @continue($key === 'general')
                        <option value="{{ $key }}" @selected(old('industry') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ix-field">
                <x-jet-label for="description" value="What does the business do?" />
                <textarea id="description" name="description" class="ix-textarea" rows="3" maxlength="600" required placeholder="A sentence or two is plenty.">{{ old('description') }}</textarea>
            </div>

            <fieldset class="ix-choice">
                <legend class="ix-label">Who can see the business page</legend>
                @foreach ($visibility as $value => $label)
                    <label class="ix-choice__opt">
                        <input type="radio" name="visibility" value="{{ $value }}" @checked(old('visibility') === $value) required>
                        <span><b>{{ \Illuminate\Support\Str::before($label, ' — ') }}</b><span class="ix-hint">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($label, ' — ')) }}</span></span>
                    </label>
                @endforeach
                <p class="ix-hint">Nothing is chosen for you.</p>
            </fieldset>

            <details @if (old('stage') || old('size_band') || old('objectives')) open @endif>
                <summary class="ix-btn ix-btn--ghost">Tell us more (optional)</summary>
                <p class="ix-hint">This only helps us suggest the platforms that fit. Skip it if you like.</p>

                <div class="ix-field">
                    <x-jet-label for="stage" value="Where is the business today?" />
                    <select id="stage" name="stage" class="ix-input ix-select">
                        <option value="">Prefer not to say</option>
                        @foreach ($stages as $key => $label)
                            <option value="{{ $key }}" @selected(old('stage') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ix-field">
                    <x-jet-label for="size_band" value="How many people work in it?" />
                    <select id="size_band" name="size_band" class="ix-input ix-select">
                        <option value="">Prefer not to say</option>
                        @foreach ($sizeBands as $key => $label)
                            <option value="{{ $key }}" @selected(old('size_band') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @include('onboarding._objectives', ['selected' => (array) old('objectives', [])])
            </details>

            <x-jet-button class="ix-entry__go">Create company and continue</x-jet-button>
        </form>

        @unless ($branding || ($another ?? false))
            <p class="ix-entry__links"><a href="{{ route('dashboard') }}">Skip for now</a></p>
        @endunless
    </x-jet-authentication-card>
</x-guest-layout>
