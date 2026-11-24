<x-guest-layout title="Set up your business">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Set up your business</h1>
        <p class="ix-entry__lede">
            @if ($branding)
                {{ $branding->name }} is for businesses. Your account is ready; name your business to continue to {{ $branding->name }}.
            @else
                Name your business to get started. You can change everything here later.
            @endif
        </p>

        @if ($errors->any())
            <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="ix-entry__form" method="POST" action="{{ route('onboarding.business.store') }}">
            @csrf

            <div class="ix-field">
                <x-jet-label for="name" value="Business name" />
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
                <x-jet-label for="stage" value="Where is the business today? (optional)" />
                <select id="stage" name="stage" class="ix-input ix-select">
                    <option value="">Prefer not to say</option>
                    @foreach ($stages as $key => $label)
                        <option value="{{ $key }}" @selected(old('stage') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ix-field">
                <x-jet-label for="size_band" value="How many people work in it? (optional)" />
                <select id="size_band" name="size_band" class="ix-input ix-select">
                    <option value="">Prefer not to say</option>
                    @foreach ($sizeBands as $key => $label)
                        <option value="{{ $key }}" @selected(old('size_band') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @include('onboarding._objectives', ['selected' => (array) old('objectives', [])])

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

            <x-jet-button class="ix-entry__go">Create business and continue</x-jet-button>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
