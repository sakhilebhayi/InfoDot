<x-jet-form-section submit="createTeam">
    <x-slot name="title">
        {{ __('Team details') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Create a new team to collaborate with others on projects.') }}
    </x-slot>

    <x-slot name="form">
        <div class="col-span-6">
            <x-jet-label value="{{ __('Team owner') }}" />

            <div class="flex items-center gap-3">
                <span class="ix-avatar" style="width: 40px; height: 40px; font-size: 15px;">
                    @if ($this->user->profile_photo_path)
                        <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}">
                    @else
                        {{ strtoupper(substr($this->user->name, 0, 1)) }}
                    @endif
                </span>

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
            <x-jet-label for="industry" value="{{ __('What does the company do?') }}" />
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
    </x-slot>

    <x-slot name="actions">
        <x-jet-button>
            {{ __('Create team') }}
        </x-jet-button>
    </x-slot>
</x-jet-form-section>
