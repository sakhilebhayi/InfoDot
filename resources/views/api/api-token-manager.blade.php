<div>
    <!-- Generate API Token -->
    <x-jet-form-section submit="createApiToken">
        <x-slot name="title">
            {{ __('Create API token') }}
        </x-slot>

        <x-slot name="description">
            {{ __('API tokens allow third-party services to authenticate with our application on your behalf.') }}
        </x-slot>

        <x-slot name="form">
            <!-- Token Name -->
            <div class="col-span-6 sm:col-span-4">
                <x-jet-label for="name" value="{{ __('Token name') }}" />
                <x-jet-input id="name" type="text" class="block w-full" wire:model.defer="createApiTokenForm.name" autofocus />
                <x-jet-input-error for="name" />
            </div>

            <!-- Token Permissions -->
            @if (Laravel\Jetstream\Jetstream::hasPermissions())
                <div class="col-span-6">
                    <x-jet-label for="permissions" value="{{ __('Permissions') }}" />

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                            <label class="flex items-center gap-2">
                                <x-jet-checkbox wire:model.defer="createApiTokenForm.permissions" :value="$permission"/>
                                <span class="text-sm" style="color: var(--ix-text-2);">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="actions">
            <x-jet-action-message on="created">
                {{ __('Created.') }}
            </x-jet-action-message>

            <x-jet-button>
                {{ __('Create') }}
            </x-jet-button>
        </x-slot>
    </x-jet-form-section>

    @if ($this->user->tokens->isNotEmpty())
        <!-- Manage API Tokens -->
        <x-jet-action-section>
            <x-slot name="title">
                {{ __('Manage API tokens') }}
            </x-slot>

            <x-slot name="description">
                {{ __('You may delete any of your existing tokens if they are no longer needed.') }}
            </x-slot>

            <!-- API Token List -->
            <x-slot name="content">
                <div>
                    @foreach ($this->user->tokens->sortBy('name') as $token)
                        <div class="flex items-center justify-between gap-3 py-3" style="{{ $loop->first ? '' : 'border-top: 1px solid var(--ix-rule);' }}">
                            <div class="min-w-0 truncate">
                                {{ $token->name }}
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                @if ($token->last_used_at)
                                    <div class="text-sm" style="color: var(--ix-text-3);">
                                        {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                    </div>
                                @endif

                                @if (Laravel\Jetstream\Jetstream::hasPermissions())
                                    <button class="ix-btn ix-btn--sm" wire:click="manageApiTokenPermissions({{ $token->id }})">
                                        {{ __('Permissions') }}
                                    </button>
                                @endif

                                <button class="ix-btn ix-btn--sm ix-btn--ghost" style="color: var(--ix-danger);" wire:click="confirmApiTokenDeletion({{ $token->id }})">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-jet-action-section>
    @endif

    <!-- Token Value Modal -->
    <x-jet-dialog-modal wire:model="displayingToken">
        <x-slot name="title">
            {{ __('API token') }}
        </x-slot>

        <x-slot name="content">
            <div>
                {{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}
            </div>

            <x-jet-input x-ref="plaintextToken" type="text" readonly :value="$plainTextToken"
                class="mt-4 block w-full" style="font-family: var(--ix-font); font-size: 13px;"
                aria-label="{{ __('API token') }}"
                autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                @showing-token-modal.window="setTimeout(() => $refs.plaintextToken.select(), 250)"
            />
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$set('displayingToken', false)" wire:loading.attr="disabled">
                {{ __('Close') }}
            </x-jet-secondary-button>
        </x-slot>
    </x-jet-dialog-modal>

    <!-- API Token Permissions Modal -->
    <x-jet-dialog-modal wire:model="managingApiTokenPermissions">
        <x-slot name="title">
            {{ __('API token permissions') }}
        </x-slot>

        <x-slot name="content">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                    <label class="flex items-center gap-2">
                        <x-jet-checkbox wire:model.defer="updateApiTokenForm.permissions" :value="$permission"/>
                        <span class="text-sm" style="color: var(--ix-text-2);">{{ $permission }}</span>
                    </label>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$set('managingApiTokenPermissions', false)" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-jet-secondary-button>

            <x-jet-button wire:click="updateApiToken" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-jet-button>
        </x-slot>
    </x-jet-dialog-modal>

    <!-- Delete Token Confirmation Modal -->
    <x-jet-confirmation-modal wire:model="confirmingApiTokenDeletion">
        <x-slot name="title">
            {{ __('Delete API token') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to delete this API token?') }}
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$toggle('confirmingApiTokenDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-jet-secondary-button>

            <x-jet-danger-button wire:click="deleteApiToken" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-jet-danger-button>
        </x-slot>
    </x-jet-confirmation-modal>
</div>
