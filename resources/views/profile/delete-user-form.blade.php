<x-jet-action-section>
    <x-slot name="title">
        {{ __('Delete account') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Permanently delete your account.') }}
    </x-slot>

    <x-slot name="content">
        @php
            // R-49: a company other people are members of does not go with its owner's account.
            $blocking = app(\App\Actions\Jetstream\DeleteUser::class)->businessesOthersDependOn(auth()->user());
        @endphp
        @if ($blocking->isNotEmpty())
            <div class="max-w-xl text-sm" style="color: var(--ix-text-2);" data-delete-blocked>
                <p style="color: var(--ix-text); font-weight: 600;">{{ trans_choice('Your account cannot be deleted yet: you own a company that other people are members of.|Your account cannot be deleted yet: you own companies that other people are members of.', $blocking->count()) }}</p>
                <p style="margin-top: 8px;">{{ __('Deleting your account would take the company away from everyone in it. For each company below, transfer its ownership to one of its members or delete the company. Then come back here.') }}</p>
            </div>
            <ul class="ix-list" style="margin-top: 16px;">
                @foreach ($blocking as $business)
                    <li class="flex flex-wrap items-center justify-between gap-3" style="padding: 10px 14px;">
                        <span class="min-w-0" style="font-weight: 600; overflow-wrap: anywhere;">{{ $business->name }} <span style="font-weight: 400; color: var(--ix-text-2);">&middot; {{ trans_choice(':n other member|:n other members', $business->users()->count(), ['n' => $business->users()->count()]) }}</span></span>
                        <a class="ix-btn ix-btn--sm" href="{{ route('teams.show', $business->id) }}#transfer">{{ __('Transfer or delete') }}</a>
                    </li>
                @endforeach
            </ul>
        @else
        <div class="max-w-xl text-sm" style="color: var(--ix-text-2);">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </div>

        <div class="mt-5">
            <x-jet-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                {{ __('Delete account') }}
            </x-jet-danger-button>
        </div>
        @endif

        <!-- Delete User Confirmation Modal -->
        <x-jet-dialog-modal wire:model="confirmingUserDeletion">
            <x-slot name="title">
                {{ __('Delete account') }}
            </x-slot>

            <x-slot name="content">
                {{ __('Are you sure you want to delete your account? Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}

                <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-jet-input type="password" class="block w-3/4"
                                placeholder="{{ __('Password') }}"
                                aria-label="{{ __('Password') }}"
                                x-ref="password"
                                wire:model.defer="password"
                                wire:keydown.enter="deleteUser" />

                    <x-jet-input-error for="password" />
                    <x-jet-input-error for="account" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-jet-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-jet-secondary-button>

                <x-jet-danger-button wire:click="deleteUser" wire:loading.attr="disabled">
                    {{ __('Delete account') }}
                </x-jet-danger-button>
            </x-slot>
        </x-jet-dialog-modal>
    </x-slot>
</x-jet-action-section>
