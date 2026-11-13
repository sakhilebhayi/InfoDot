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
            {{-- The picture is checked in the browser (type and size) before any of it is uploaded; only a picture that passes is
                 sent, with $wire.upload. The server checks again when the form is saved (UpdateUserProfileInformation). --}}
            <div x-data="{ photoName: null, photoPreview: null, photoError: null, photoBusy: false, photoMax: {{ \App\Services\Profile\ProfilePhoto::MAX_KILOBYTES * 1024 }} }" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->

                <x-jet-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div class="relative" x-show="! photoPreview">
                    <input type="file" id="photo" accept="image/jpeg,image/png,image/webp" class="absolute h-20 w-20 cursor-pointer opacity-0" wire:key="photo" x-ref="photo" aria-describedby="photo-hint photo-check" x-on:change="
                        const file = $refs.photo.files[0];
                        if (! file) { return; }
                        photoError = null;
                        if (! ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                            photoError = 'Choose a picture: JPG, PNG or WebP.';
                        } else if (file.size > photoMax) {
                            photoError = 'That picture is ' + (file.size / 1048576).toFixed(1) + ' MB. The limit is ' + Math.round(photoMax / 1048576) + ' MB. Choose a smaller one.';
                        }
                        if (photoError) { $refs.photo.value = ''; return; }
                        photoName = file.name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            photoPreview = e.target.result;
                        };
                        reader.readAsDataURL(file);
                        photoBusy = true;
                        $wire.upload('photo', file, () => { photoBusy = false; }, () => {
                            photoBusy = false;
                            photoPreview = null;
                            $refs.photo.value = '';
                            photoError = 'The picture did not upload. Check your connection and try again.';
                        });
                    " />
                    {{-- The saved picture when it can load; the initial when there is none or its file is gone. --}}
                    @if ($photo = $this->user->photoUrl())
                        <img src="{{ $photo }}" alt="{{ $this->user->name }}" class="h-20 w-20 cursor-pointer rounded-full object-cover" style="border: 1px solid var(--ix-rule-strong);">
                    @else
                        <span class="ix-avatar" style="width: 80px; height: 80px; font-size: 28px; cursor: pointer;">{{ $this->user->initial() }}</span>
                    @endif
                </div>

                <!-- New Profile Photo Preview -->
                <div x-show="photoPreview">
                    <span class="block h-20 w-20 rounded-full bg-cover bg-center bg-no-repeat" style="border: 1px solid var(--ix-rule-strong);"
                          x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <p class="ix-hint" id="photo-hint">Select the picture to choose a new photo. JPG, PNG or WebP, up to {{ (int) (\App\Services\Profile\ProfilePhoto::MAX_KILOBYTES / 1024) }} MB.</p>
                <p class="ix-hint" x-show="photoBusy" x-cloak role="status">Uploading…</p>
                <p class="ix-error" id="photo-check" role="alert" x-show="photoError" x-text="photoError" x-cloak></p>

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
