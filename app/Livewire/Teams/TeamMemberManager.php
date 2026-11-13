<?php

namespace App\Livewire\Teams;

use App\Actions\Jetstream\InviteTeamMember;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager as JetstreamTeamMemberManager;

/**
 * Jetstream's component hides the "cancel invitation" button from people who may not remove members, but the action itself checks nothing,
 * so any member could call it from the browser and delete a company's pending invitations (and free the seats they hold).
 */
class TeamMemberManager extends JetstreamTeamMemberManager
{
    public function cancelTeamInvitation($invitationId)
    {
        Gate::authorize('removeTeamMember', $this->team);

        parent::cancelTeamInvitation($invitationId);
    }

    /** Sends an invitation again, which starts a new 14 days. Only for people who may invite, and only for this company's invitations. */
    public function resendTeamInvitation(int $invitationId): void
    {
        $this->resetErrorBag();

        $invitation = $this->team->teamInvitations()->whereKey($invitationId)->firstOrFail();
        app(InviteTeamMember::class)->resend($this->user, $this->team, $invitation);

        $this->team = $this->team->fresh();
        $this->dispatch('invitation-resent');
    }
}
