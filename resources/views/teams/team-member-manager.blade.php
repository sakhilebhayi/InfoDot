<div>
    @php($newLink = $newLink ?? null)
    @php($bulkResult = $bulkResult ?? [])
    @php($lic = app(\App\Services\Licensing\SeatLedger::class)->summary($team))
    @if (Gate::check('addTeamMember', $team) && $lic['applies'] && $lic['available'] < 1)
        {{-- R-53: no licence is free, so the way to add someone is to buy one. This is the upgrade moment: why, what it includes, the cost,
             monthly or yearly, and how to buy more. Nothing is withheld to push a sale, and no email is asked for until there is a licence. --}}
        <x-jet-action-section>
            <x-slot name="title">{{ __('Add a person') }}</x-slot>
            <x-slot name="description">{{ __('Your free licence covers you. Each extra person needs a licence.') }}</x-slot>
            <x-slot name="content">
                <div data-upgrade-panel>
                    @include('licences._why', ['team' => $team, 'price' => $lic['price'], 'bands' => app(\App\Services\Licensing\LicencePricing::class)->bandText()])
                    @if (Gate::check('changeLicence', $team))
                        <div class="ix-actions"><a class="ix-btn ix-btn--primary" href="{{ route('licences.buy', $team->uuid) }}" data-upgrade-buy>{{ $lic['price'] ? __('Choose licences') : __('See how licences work') }}</a></div>
                    @else
                        <p class="ix-hint" data-upgrade-ask-owner>{{ __('Only the owner can buy licences. Ask :owner to buy one, and you can invite the person once it appears here.', ['owner' => $team->owner?->name ?? __('the owner')]) }}</p>
                    @endif
                    @if ($lic['reserved'] > 0)<p class="ix-hint">{{ __('You can also free a licence by cancelling an invitation that has not been answered, below.') }}</p>@endif
                </div>
            </x-slot>
        </x-jet-action-section>
    @elseif (Gate::check('addTeamMember', $team))
        <!-- Add Team Member -->
        <x-jet-form-section submit="addTeamMember">
            <x-slot name="title">
                {{ __('Add a person') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Invite someone to your company so they can work in it with you.') }}
            </x-slot>

            <x-slot name="form">
                <div class="col-span-6">
                    <div class="max-w-xl text-sm" style="color: var(--ix-text-2);">
                        {{ __('Enter the email address of the person you want to add to this company.') }}
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
                        <x-jet-label id="add-member-role" value="{{ __('Role') }}" />
                        <x-jet-input-error for="role" />

                        <div class="relative z-0 overflow-hidden" role="group" aria-labelledby="add-member-role" style="border: 1px solid var(--ix-rule-strong); border-radius: var(--ix-radius);">
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

    @if (Gate::check('addTeamMember', $team) && ! $team->personal_team && ! ($lic['applies'] && $lic['available'] < 1))
        <!-- Invite several people at once (R-62 phase 4) -->
        <x-jet-action-section>
            <x-slot name="title">{{ __('Invite several people') }}</x-slot>
            <x-slot name="description">{{ __('Paste a list of email addresses, up to :max at a time. Each person gets their own invitation and holds a licence until they answer.', ['max' => \App\Actions\Jetstream\InviteManyTeamMembers::MAX]) }}</x-slot>
            <x-slot name="content">
                <form wire:submit.prevent="inviteMany" data-bulk-invite>
                    <x-jet-label for="bulk-emails" value="{{ __('Email addresses') }}" />
                    <textarea id="bulk-emails" class="ix-textarea" rows="4" wire:model.defer="bulkEmails" placeholder="{{ __('One per line, or separated by commas') }}"></textarea>
                    @php($bulkRoles = \App\Support\TeamRoles::assignableBy(auth()->user(), $team))
                    <x-jet-label for="bulk-role" value="{{ __('They join as') }}" />
                    <select class="ix-select" id="bulk-role" wire:model.defer="bulkRole">
                        @foreach ($bulkRoles as $r)<option value="{{ $r }}">{{ ucfirst($r) }}</option>@endforeach
                    </select>
                    <div class="ix-actions"><x-jet-button wire:loading.attr="disabled">{{ __('Send invitations') }}</x-jet-button></div>
                </form>

                @if (! empty($bulkResult))
                    <div data-bulk-result role="status">
                        @if (count($bulkResult['invited']))<p>{{ trans_choice('{1} 1 invitation sent.|[2,*] :count invitations sent.', count($bulkResult['invited'])) }}</p>@endif
                        @if ($bulkResult['too_many'])<p class="ix-hint">{{ __('Only the first :max addresses were used. Send the rest in a second batch.', ['max' => \App\Actions\Jetstream\InviteManyTeamMembers::MAX]) }}</p>@endif
                        @if (count($bulkResult['skipped']))
                            <p>{{ __('These were not invited, and are still in the box so you can fix them:') }}</p>
                            <ul>@foreach ($bulkResult['skipped'] as $row)<li>{{ $row['email'] }}: {{ $row['reason'] }}</li>@endforeach</ul>
                        @endif
                    </div>
                @endif
            </x-slot>
        </x-jet-action-section>
    @endif

    @if (Gate::check('addTeamMember', $team) && ! $team->personal_team && \App\Support\TeamRoles::manages(auth()->user(), $team))
        <!-- Shareable link (R-62 phase 4) -->
        @php($links = \App\Models\TeamInviteLink::where('team_id', $team->id)->latest('id')->limit(10)->get())
        <x-jet-action-section>
            <x-slot name="title">{{ __('Invitation link') }}</x-slot>
            <x-slot name="description">{{ __('Anyone with the link can ask to join as a member once they have signed in with a confirmed account. It uses a licence for each person who joins, and stops working when it runs out, reaches its limit or you turn it off.') }}</x-slot>
            <x-slot name="content">
                @if ($newLink)
                    <div data-new-link>
                        <p>{{ __('Copy this link now. For safety it is shown only once.') }}</p>
                        <input type="text" class="ix-input" readonly value="{{ $newLink }}" x-data x-on:focus="$el.select()" aria-label="{{ __('Invitation link') }}">
                    </div>
                @endif

                <form wire:submit.prevent="createInviteLink" data-make-link>
                    <x-jet-label for="link-days" value="{{ __('Works for (days)') }}" />
                    <input class="ix-input" id="link-days" type="number" min="1" max="{{ \App\Models\TeamInviteLink::MAX_DAYS }}" wire:model.defer="linkDays">
                    <x-jet-label for="link-uses" value="{{ __('Most people who may use it (optional)') }}" />
                    <input class="ix-input" id="link-uses" type="number" min="1" max="{{ \App\Models\TeamInviteLink::MAX_USES_CEILING }}" wire:model.defer="linkMaxUses">
                    <div class="ix-actions"><x-jet-button>{{ __('Make a link') }}</x-jet-button></div>
                </form>

                @if ($links->isNotEmpty())
                    <ul data-link-list>
                        @foreach ($links as $l)
                            <li>
                                {{ __('Link made :date', ['date' => $l->created_at->timezone(config('app.timezone'))->format('j M Y')]) }}:
                                {{ __(['active' => 'working', 'expired' => 'ran out', 'revoked' => 'turned off', 'used_up' => 'limit reached'][$l->status()]) }},
                                {{ trans_choice('{0} nobody has joined|{1} 1 person joined|[2,*] :count people joined', $l->uses) }}{{ $l->max_uses ? ' '.__('of :max', ['max' => $l->max_uses]) : '' }}.
                                @if ($l->status() === 'active')
                                    <button type="button" class="ix-btn ix-btn--sm" wire:click="revokeInviteLink({{ $l->id }})">{{ __('Turn off') }}</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-slot>
        </x-jet-action-section>
    @endif

    @if ($team->teamInvitations->isNotEmpty() && Gate::check('addTeamMember', $team))
        <!-- Team Member Invitations -->
        <x-jet-action-section>
            <x-slot name="title">
                {{ __('Invitations not yet answered') }}
            </x-slot>

            <x-slot name="description">
                {{ __('These people have been sent an invitation email. They join the company by accepting it.') }}
            </x-slot>

            <x-slot name="content">
                <div>
                    @foreach ($team->teamInvitations as $invitation)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3" style="{{ $loop->first ? '' : 'border-top: 1px solid var(--ix-rule);' }}">
                            <div class="min-w-0">
                                <div class="truncate" style="color: var(--ix-text-2);">{{ $invitation->email }}</div>
                                <div class="text-xs" style="color: var(--ix-text-3);">
                                    @if ($invitation->isExpired())
                                        <span class="ix-chip ix-chip--open">{{ __('Expired') }}</span>
                                        {{ __('Ran out on :date and holds no licence. Send it again to give it another :days days.', ['date' => $invitation->expires_at->timezone(config('app.timezone'))->format('j M Y'), 'days' => config('licensing.invitation_days', 14)]) }}
                                    @elseif ($invitation->expires_at)
                                        {{ __('Can be accepted until :date.', ['date' => $invitation->expires_at->timezone(config('app.timezone'))->format('j M Y')]) }}
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" class="ix-btn ix-btn--sm" wire:click="resendTeamInvitation({{ $invitation->id }})" wire:loading.attr="disabled" wire:target="resendTeamInvitation({{ $invitation->id }})"
                                        aria-label="{{ __('Send the invitation to :email again', ['email' => $invitation->email]) }}">
                                    {{ __('Send again') }}
                                </button>
                                @if (Gate::check('removeTeamMember', $team))
                                    <!-- Cancel Team Invitation -->
                                    <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" style="color: var(--ix-danger);"
                                                        wire:click="cancelTeamInvitation({{ $invitation->id }})"
                                                        aria-label="{{ __('Cancel the invitation to :email', ['email' => $invitation->email]) }}">
                                        {{ __('Cancel') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <x-jet-action-message on="invitation-resent">{{ __('Sent again. It can be accepted for :days days.', ['days' => config('licensing.invitation_days', 14)]) }}</x-jet-action-message>
                </div>
            </x-slot>
        </x-jet-action-section>
    @endif

    @if ($team->users->isNotEmpty())
        <!-- Manage Team Members -->
        <x-jet-action-section>
            <x-slot name="title">
                {{ __('People in the company') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Everyone who is part of this company.') }}
            </x-slot>

            <!-- Team Member List -->
            <x-slot name="content">
                <div>
                    @foreach ($team->users->sortBy('name') as $user)
                        <div class="flex items-center justify-between gap-3 py-3" style="{{ $loop->first ? '' : 'border-top: 1px solid var(--ix-rule);' }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-ix.avatar :user="$user" :alt="$user->name" />
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
            {{ __('Leave company') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to leave this company?') }}
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
            {{ __('Remove person') }}
        </x-slot>

        <x-slot name="content">
            @php($leaving = $teamMemberIdBeingRemoved ? $team->users->firstWhere('id', $teamMemberIdBeingRemoved) : null)
            @if ($leaving)
                {{ __(':name loses access to :team on InfoDot and on every platform it has switched on, straight away. Their account and anything they wrote stay; nothing is deleted. The licence they held becomes free for you to use for someone else.', ['name' => $leaving->name, 'team' => $team->name]) }}
            @else
                {{ __('Are you sure you would like to remove this person from the company?') }}
            @endif
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
