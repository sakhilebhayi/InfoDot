<x-jet-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->

                <x-jet-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div class="relative" x-show="! photoPreview">
                    <input type="file" id="photo" class="absolute h-20 w-20 cursor-pointer opacity-0" wire:model="photo" wire:key="photo" x-ref="photo" x-on:change="
                        photoName = $refs.photo.files[0].name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            photoPreview = e.target.result;
                        };

                        reader.readAsDataURL($refs.photo.files[0]);
                    " />
                    @if ($this->user->profile_photo_path)
                        <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="h-20 w-20 cursor-pointer rounded-full object-cover" style="border: 1px solid var(--ix-rule-strong);">
                    @else
                        <span class="ix-avatar" style="width: 80px; height: 80px; font-size: 28px; cursor: pointer;">{{ strtoupper(substr($this->user->name, 0, 1)) }}</span>
                    @endif
                </div>

                <!-- New Profile Photo Preview -->
                <div x-show="photoPreview">
                    <span class="block h-20 w-20 rounded-full bg-cover bg-center bg-no-repeat" style="border: 1px solid var(--ix-rule-strong);"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <p class="ix-hint">{{ __('Select the picture to choose a new photo.') }}</p>

                @if ($this->user->profile_photo_path)
                    <x-jet-secondary-button type="button" class="mt-3" wire:click="deleteProfilePhoto">
                        {{ __('Remove photo') }}
                    </x-jet-secondary-button>
                @endif

                <x-jet-input-error for="photo" />
            </div>
        @endif

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="name" value="{{ __('Name') }}" />
            <x-jet-input id="name" type="text" class="block w-full" wire:model.defer="state.name" autocomplete="name" />
            <x-jet-input-error for="name" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-jet-label for="email" value="{{ __('Email') }}" />
            <x-jet-input id="email" type="email" class="block w-full" wire:model.defer="state.email" />
            <x-jet-input-error for="email" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-jet-action-message on="saved">
            {{ __('Saved.') }}
        </x-jet-action-message>

        <x-jet-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-jet-button>
    </x-slot>
</x-jet-form-section>
