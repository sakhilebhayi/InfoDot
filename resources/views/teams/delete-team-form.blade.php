<x-jet-action-section>
    <x-slot name="title">
        {{ __('Delete company') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Permanently delete this company.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm" style="color: var(--ix-text-2);">
            {{ __('Once a company is deleted, everything it owns, its business page included, is permanently deleted. Before deleting it, download anything you want to keep.') }}
        </div>

        <div class="mt-5">
            <x-jet-danger-button wire:click="$toggle('confirmingTeamDeletion')" wire:loading.attr="disabled">
                {{ __('Delete company') }}
            </x-jet-danger-button>
        </div>

        <!-- Delete team Confirmation Modal -->
        <x-jet-confirmation-modal wire:model="confirmingTeamDeletion">
            <x-slot name="title">
                {{ __('Delete company') }}
            </x-slot>

            <x-slot name="content">
                {{ __('Are you sure you want to delete this company? Everything it owns, its business page included, will be permanently deleted.') }}
            </x-slot>

            <x-slot name="footer">
                <x-jet-secondary-button wire:click="$toggle('confirmingTeamDeletion')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-jet-secondary-button>

                <x-jet-danger-button wire:click="deleteTeam" wire:loading.attr="disabled">
                    {{ __('Delete company') }}
                </x-jet-danger-button>
            </x-slot>
        </x-jet-confirmation-modal>
    </x-slot>
</x-jet-action-section>
