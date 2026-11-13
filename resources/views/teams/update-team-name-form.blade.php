<x-jet-form-section submit="updateTeamName">
    <x-slot name="title">
        {{ __('Team name') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The team\'s name and owner information.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Team Owner Information -->
        <div class="col-span-6">
            <x-jet-label value="{{ __('Team owner') }}" />

            <div class="flex items-center gap-3">
                <x-ix.avatar :user="$team->owner" :size="40" :alt="$team->owner->name" />

                <div class="min-w-0 leading-tight">
                    <div style="font-weight: 600;">{{ $team->owner->name }}</div>
                    <div class="text-sm" style="color: var(--ix-text-2);">{{ $team->owner->email }}</div>
                </div>
            </div>
        </div>

        <!-- Team Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="team_name" value="{{ __('Team name') }}" />

            <x-jet-input id="team_name"
                        type="text"
                        class="block w-full"
                        wire:model.defer="state.name"
                        :disabled="! Gate::check('update', $team)" />

            <x-jet-input-error for="name" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="industry" value="{{ __('What does the company do?') }}" />
            <select id="industry" class="ix-input ix-select block w-full" wire:model.defer="state.industry" @disabled(! Gate::check('update', $team))>
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

    @if (Gate::check('update', $team))
        <x-slot name="actions">
            <x-jet-action-message on="saved">
                {{ __('Saved.') }}
            </x-jet-action-message>

            <x-jet-button>
                {{ __('Save') }}
            </x-jet-button>
        </x-slot>
    @endif
</x-jet-form-section>
