<x-jet-form-section submit="transferOwnership">
    <x-slot name="title">
        {{ __('Transfer ownership') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Hand :team to someone who is already in it. Nobody loses access and nothing the company owns is moved.', ['team' => $team->name]) }}
    </x-slot>

    <x-slot name="form">
        @if ($candidates->isEmpty())
            <div class="col-span-6">
                <p class="max-w-xl text-sm" style="color: var(--ix-text-2);">
                    {{ __('You are the only person in :team, so there is nobody to hand it to yet. Add the person under People and roles; once they have joined, you can choose them here.', ['team' => $team->name]) }}
                </p>
                <p style="margin-top: 16px;"><a class="ix-btn" href="#members">{{ __('Go to People and roles') }}</a></p>
            </div>
        @else
            <div class="col-span-6">
                <p class="max-w-xl text-sm" style="color: var(--ix-text-2);">
                    {{ __('The person you choose becomes the owner: they alone can change seats, transfer the company again or delete it. You stay in the company with the role they hold today.') }}
                </p>
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-jet-label for="new_owner" value="{{ __('New owner') }}" />
                <select id="new_owner" class="ix-input ix-select block w-full" wire:model="newOwner" required @error('new_owner') aria-invalid="true" aria-describedby="new_owner_error" @enderror>
                    <option value="">{{ __('Choose a person') }}</option>
                    @foreach ($candidates as $person)
                        <option value="{{ $person->id }}">{{ $person->name }} ({{ Laravel\Jetstream\Jetstream::findRole(\App\Support\TeamRoles::normalise($person->membership->role))?->name ?? ucfirst($person->membership->role) }})</option>
                    @endforeach
                </select>
                @error('new_owner')<p class="ix-error" id="new_owner_error">{{ $message }}</p>@enderror
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-jet-label for="transfer_password" value="{{ __('Your password') }}" />
                <x-jet-input id="transfer_password" type="password" class="block w-full" autocomplete="current-password" wire:model="password" required />
                <p class="ix-hint">{{ __('Asked for again because this cannot be undone by you: only the new owner can hand the company back.') }}</p>
                <x-jet-input-error for="password" />
            </div>
        @endif
    </x-slot>

    @if ($candidates->isNotEmpty())
        <x-slot name="actions">
            <x-jet-button wire:loading.attr="disabled" wire:target="transferOwnership">
                {{ __('Transfer ownership') }}
            </x-jet-button>
        </x-slot>
    @endif
</x-jet-form-section>
