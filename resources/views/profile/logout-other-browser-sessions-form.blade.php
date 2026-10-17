<x-jet-action-section>
    <x-slot name="title">
        {{ __('Browser sessions') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage and log out your active sessions on other browsers and devices.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm" style="color: var(--ix-text-2);">
            {{ __('If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password.') }}
        </div>

        @if (count($this->sessions) > 0)
            <div class="mt-5">
                <!-- Other Browser Sessions -->
                @foreach ($this->sessions as $session)
                    <div class="flex items-center gap-3 py-3" style="border-top: 1px solid var(--ix-rule);{{ $loop->last ? ' border-bottom: 1px solid var(--ix-rule);' : '' }}">
                        <span class="material-symbols-rounded" aria-hidden="true" style="color: var(--ix-text-2);">{{ $session->agent->isDesktop() ? 'desktop_windows' : 'smartphone' }}</span>

                        <div class="min-w-0">
                            <div class="text-sm" style="color: var(--ix-text);">
                                {{ $session->agent->platform() }} - {{ $session->agent->browser() }}
                            </div>

                            <div class="text-xs" style="color: var(--ix-text-3);">
                                <span class="ix-fig">{{ $session->ip_address }}</span>,

                                @if ($session->is_current_device)
                                    <span style="color: var(--ix-ok); font-weight: 600;">{{ __('This device') }}</span>
                                @else
                                    {{ __('Last active') }} {{ $session->last_active }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-5 flex items-center gap-3">
            <x-jet-secondary-button wire:click="confirmLogout" wire:loading.attr="disabled">
                {{ __('Log out other browser sessions') }}
            </x-jet-secondary-button>

            <x-jet-action-message on="loggedOut">
                {{ __('Done.') }}
            </x-jet-action-message>
        </div>

        <!-- Log Out Other Devices Confirmation Modal -->
        <x-jet-dialog-modal wire:model="confirmingLogout">
            <x-slot name="title">
                {{ __('Log out other browser sessions') }}
            </x-slot>

            <x-slot name="content">
                {{ __('Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.') }}

                <div class="mt-4" x-data="{}" x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-jet-input type="password" class="block w-3/4"
                                placeholder="{{ __('Password') }}"
                                aria-label="{{ __('Password') }}"
                                x-ref="password"
                                wire:model.defer="password"
                                wire:keydown.enter="logoutOtherBrowserSessions" />

                    <x-jet-input-error for="password" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-jet-secondary-button wire:click="$toggle('confirmingLogout')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-jet-secondary-button>

                <x-jet-button wire:click="logoutOtherBrowserSessions"
                            wire:loading.attr="disabled">
                    {{ __('Log out other browser sessions') }}
                </x-jet-button>
            </x-slot>
        </x-jet-dialog-modal>
    </x-slot>
</x-jet-action-section>
