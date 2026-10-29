<div>
    @if (Gate::check('addTeamMember', $team))
        <!-- Add Team Member -->
        <x-jet-form-section submit="addTeamMember">
            <x-slot name="title">
                {{ __('Add team member') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Add a new team member to your team, allowing them to collaborate with you.') }}
            </x-slot>

            <x-slot name="form">
                <div class="col-span-6">
                    <div class="max-w-xl text-sm" style="color: var(--ix-text-2);">
                        {{ __('Please provide the email address of the person you would like to add to this team.') }}
                    </div>
                </div>

                <!-- Member Email -->
                <div class="col-span-6 sm:col-span-4">
                    <x-jet-label for="email" value="{{ __('Email') }}" />
                    <x-jet-input id="email" type="email" class="block w-full" wire:model.defer="addTeamMemberForm.email" />
                    <x-jet-input-error for="email" />
                </div>

                <!-- Role -->
                @if (count($this->roles) > 0)
                    <div class="col-span-6 lg:col-span-4">
                        <x-jet-label for="role" value="{{ __('Role') }}" />
                        <x-jet-input-error for="role" />

                        <div class="relative z-0 overflow-hidden" style="border: 1px solid var(--ix-rule-strong); border-radius: var(--ix-radius);">
                            @foreach (collect($this->roles)->filter(fn ($r) => in_array($r->key, \App\Support\TeamRoles::assignableBy(auth()->user(), $team), true))->values() as $index => $role)
                                @php($selected = isset($addTeamMemberForm['role']) && $addTeamMemberForm['role'] === $role->key)
                                <button type="button" class="relative flex w-full items-start justify-between gap-3 px-4 py-3 text-left focus:z-10"
                                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                style="{{ $index > 0 ? 'border-top: 1px solid var(--ix-rule);' : '' }} {{ $selected ? 'background: var(--ix-signal-wash);' : '' }}"
                                                wire:click="$set('addTeamMemberForm.role', '{{ $role->key }}')">
                                    <div>
                                        <!-- Role Name -->
                                        <div class="text-sm" style="font-weight: {{ $selected ? '600' : '500' }};">
                                            {{ $role->name }}
                                        </div>

                                        <!-- Role Description -->
                                        <div class="mt-1 text-xs" style="color: var(--ix-text-2);">
                                            {{ $role->description }}
                                        </div>
                                    </div>

                                    @if ($selected)
                                        <span class="material-symbols-rounded" aria-hidden="true" style="color: var(--ix-signal-text);">check</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-slot>

            <x-slot name="actions">
                <x-jet-action-message on="saved">
                    {{ __('Added.') }}
                </x-jet-action-message>

                <x-jet-button>
                    {{ __('Add') }}
                </x-jet-button>
            </x-slot>
        </x-jet-form-section>
    @endif

    @if ($team->teamInvitations->isNotEmpty() && Gate::check('addTeamMember', $team))
        <!-- Team Member Invitations -->
        <x-jet-action-section>
            <x-slot name="title">
                {{ __('Pending team invitations') }}
            </x-slot>

            <x-slot name="description">
                {{ __('These people have been invited to your team and have been sent an invitation email. They may join the team by accepting the email invitation.') }}
            </x-slot>

            <x-slot name="content">
                <div>
                    @foreach ($team->teamInvitations as $invitation)
                        <div class="flex items-center justify-between gap-3 py-3" style="{{ $loop->first ? '' : 'border-top: 1px solid var(--ix-rule);' }}">
                            <div class="min-w-0 truncate" style="color: var(--ix-text-2);">{{ $invitation->email }}</div>

                            <div class="flex items-center">
                                @if (Gate::check('removeTeamMember', $team))
                                    <!-- Cancel Team Invitation -->
                                    <button class="ix-btn ix-btn--sm ix-btn--ghost" style="color: var(--ix-danger);"
                                                        wire:click="cancelTeamInvitation({{ $invitation->id }})">
                                        {{ __('Cancel') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-jet-action-section>
    @endif

    @if ($team->users->isNotEmpty())
        <!-- Manage Team Members -->
        <x-jet-action-section>
            <x-slot name="title">
                {{ __('Team members') }}
            </x-slot>

            <x-slot name="description">
                {{ __('All of the people that are part of this team.') }}
            </x-slot>

            <!-- Team Member List -->
            <x-slot name="content">
                <div>
                    @foreach ($team->users->sortBy('name') as $user)
                        <div class="flex items-center justify-between gap-3 py-3" style="{{ $loop->first ? '' : 'border-top: 1px solid var(--ix-rule);' }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="ix-avatar">
                                    @if ($user->profile_photo_path)
                                        <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}">
                                    @else
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    @endif
                                </span>
                                <div class="min-w-0 truncate">{{ $user->name }}</div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <!-- Manage Team Member Role -->
                                @if (Gate::check('updateTeamMember', $team) && Laravel\Jetstream\Jetstream::hasRoles())
                                    <button class="ix-btn ix-btn--sm" wire:click="manageRole('{{ $user->id }}')">
                                        {{ (Laravel\Jetstream\Jetstream::findRole(\App\Support\TeamRoles::normalise($user->membership->role))?->name ?? ucfirst($user->membership->role)) }}
                                    </button>
                                @elseif (Laravel\Jetstream\Jetstream::hasRoles())
                                    <div class="text-sm" style="color: var(--ix-text-2);">
                                        {{ (Laravel\Jetstream\Jetstream::findRole(\App\Support\TeamRoles::normalise($user->membership->role))?->name ?? ucfirst($user->membership->role)) }}
                                    </div>
                                @endif

                                <!-- Leave Team -->
                                @if ($this->user->id === $user->id)
                                    <button class="ix-btn ix-btn--sm ix-btn--ghost" style="color: var(--ix-danger);" wire:click="$toggle('confirmingLeavingTeam')">
                                        {{ __('Leave') }}
                                    </button>

                                <!-- Remove Team Member -->
                                @elseif (Gate::check('removeTeamMember', $team))
                                    <button class="ix-btn ix-btn--sm ix-btn--ghost" style="color: var(--ix-danger);" wire:click="confirmTeamMemberRemoval('{{ $user->id }}')">
                                        {{ __('Remove') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-jet-action-section>
    @endif

    <!-- Role Management Modal -->
    <x-jet-dialog-modal wire:model="currentlyManagingRole">
        <x-slot name="title">
            {{ __('Manage role') }}
        </x-slot>

        <x-slot name="content">
            <div class="relative z-0 overflow-hidden" style="border: 1px solid var(--ix-rule-strong); border-radius: var(--ix-radius);">
                @foreach (collect($this->roles)->filter(fn ($r) => in_array($r->key, \App\Support\TeamRoles::assignableBy(auth()->user(), $team), true))->values() as $index => $role)
                    @php($selected = $currentRole == $role->key)
                    <button type="button" class="relative flex w-full items-start justify-between gap-3 px-4 py-3 text-left focus:z-10"
                                    aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                    style="{{ $index > 0 ? 'border-top: 1px solid var(--ix-rule);' : '' }} {{ $selected ? 'background: var(--ix-signal-wash);' : '' }}"
                                    wire:click="$set('currentRole', '{{ $role->key }}')">
                        <div>
                            <!-- Role Name -->
                            <div class="text-sm" style="font-weight: {{ $selected ? '600' : '500' }}; color: var(--ix-text);">
                                {{ $role->name }}
                            </div>

                            <!-- Role Description -->
                            <div class="mt-1 text-xs" style="color: var(--ix-text-2);">
                                {{ $role->description }}
                            </div>
                        </div>

                        @if ($selected)
                            <span class="material-symbols-rounded" aria-hidden="true" style="color: var(--ix-signal-text);">check</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="stopManagingRole" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-jet-secondary-button>

            <x-jet-button wire:click="updateRole" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-jet-button>
        </x-slot>
    </x-jet-dialog-modal>

    <!-- Leave Team Confirmation Modal -->
    <x-jet-confirmation-modal wire:model="confirmingLeavingTeam">
        <x-slot name="title">
            {{ __('Leave team') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to leave this team?') }}
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$toggle('confirmingLeavingTeam')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-jet-secondary-button>

            <x-jet-danger-button wire:click="leaveTeam" wire:loading.attr="disabled">
                {{ __('Leave') }}
            </x-jet-danger-button>
        </x-slot>
    </x-jet-confirmation-modal>

    <!-- Remove Team Member Confirmation Modal -->
    <x-jet-confirmation-modal wire:model="confirmingTeamMemberRemoval">
        <x-slot name="title">
            {{ __('Remove team member') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to remove this person from the team?') }}
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$toggle('confirmingTeamMemberRemoval')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-jet-secondary-button>

            <x-jet-danger-button wire:click="removeTeamMember" wire:loading.attr="disabled">
                {{ __('Remove') }}
            </x-jet-danger-button>
        </x-slot>
    </x-jet-confirmation-modal>
</div>
