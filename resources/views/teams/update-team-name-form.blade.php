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
                <span class="ix-avatar" style="width: 40px; height: 40px; font-size: 15px;">
                    @if ($team->owner->profile_photo_path)
                        <img src="{{ $team->owner->profile_photo_url }}" alt="{{ $team->owner->name }}">
                    @else
                        {{ strtoupper(substr($team->owner->name, 0, 1)) }}
                    @endif
                </span>

                <div class="min-w-0 leading-tight">
                    <div style="font-weight: 600;">{{ $team->owner->name }}</div>
                    <div class="text-sm" style="color: var(--ix-text-2);">{{ $team->owner->email }}</div>
                </div>
            </div>
        </div>

        <!-- Team Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="name" value="{{ __('Team name') }}" />

            <x-jet-input id="name"
                        type="text"
                        class="block w-full"
                        wire:model.defer="state.name"
                        :disabled="! Gate::check('update', $team)" />

            <x-jet-input-error for="name" />
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
