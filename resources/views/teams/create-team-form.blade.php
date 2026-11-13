<x-jet-form-section submit="createTeam">
    <x-slot name="title">
        {{ __('Team details') }}
    </x-slot>

    <x-slot name="description">
        Name the business, say what it does and choose who can find it. All three can be changed later.
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6">
            <x-jet-label value="{{ __('Team owner') }}" />

            <div class="flex items-center gap-3">
                <x-ix.avatar :user="$this->user" :size="40" :alt="$this->user->name" />

                <div class="min-w-0 leading-tight">
                    <div style="font-weight: 600;">{{ $this->user->name }}</div>
                    <div class="text-sm" style="color: var(--ix-text-2);">{{ $this->user->email }}</div>
                </div>
            </div>
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="name" value="{{ __('Team name') }}" />
            <x-jet-input id="name" type="text" class="block w-full" wire:model.defer="state.name" autofocus />
            <x-jet-input-error for="name" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="industry" value="Industry" />
            <select id="industry" class="ix-input ix-select block w-full" wire:model.defer="state.industry">
                <option value="">{{ __('General business') }}</option>
                @foreach (\App\Support\Industry::options() as $key => $label)
                    @continue($key === 'general')
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>
            <p class="ix-hint">{{ __('Search, the question form and empty pages suggest examples that fit your business. You can change this any time.') }}</p>
            <x-jet-input-error for="industry" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="description" value="What the business does" />
            <textarea id="description" class="ix-textarea block w-full" rows="4" maxlength="2000" wire:model.defer="state.description"></textarea>
            <p class="ix-hint">A sentence or two a customer would understand. Businesses lists a business once it says this.</p>
            <x-jet-input-error for="description" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <fieldset class="ix-choice">
                <legend class="ix-label">Who can see the business</legend>
                @foreach (\App\Models\TeamProfile::VISIBILITY as $value => $label)
                    <label class="ix-choice__opt">
                        <input type="radio" name="visibility" value="{{ $value }}" wire:model.defer="state.visibility">
                        <span><b>{{ \Illuminate\Support\Str::before($label, ' — ') }}</b><span class="ix-hint">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($label, ' — ')) }}</span></span>
                    </label>
                @endforeach
            </fieldset>
            <x-jet-input-error for="visibility" />
            <p class="ix-hint">Nothing is chosen for you. InfoDot never shows your team’s own accounts or staff contact details, whatever you pick.</p>
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-jet-button>
            {{ __('Create team') }}
        </x-jet-button>
    </x-slot>
</x-jet-form-section>
