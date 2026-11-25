<?php

namespace App\Livewire\Teams;

use App\Actions\Jetstream\InviteManyTeamMembers;
use App\Actions\Jetstream\InviteTeamMember;
use App\Models\TeamInviteLink;
use App\Services\Licensing\SeatLedger;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\Auth;
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
        app(InviteTeamMember::class)->resend(Auth::user(), $this->team, $invitation);

        $this->team = $this->team->fresh();
        $this->dispatch('invitation-resent');
    }

    /** What the last bulk invitation did, per address. */
    public array $bulkResult = [];

    public string $bulkEmails = '';

    public string $bulkRole = 'member';

    /** The link just made. It is shown once; only its hash is stored. */
    public ?string $newLink = null;

    public int $linkDays = 7;

    public ?int $linkMaxUses = null;

    /** Invites everyone in the box, one invitation each, and says what happened to each address. */
    public function inviteMany(): void
    {
        $this->resetErrorBag();
        $this->bulkResult = [];

        Gate::authorize('addTeamMember', $this->team);

        $role = TeamRoles::normalise($this->bulkRole);
        $result = app(InviteManyTeamMembers::class)->invite(Auth::user(), $this->team, $this->bulkEmails, $role);

        $this->bulkResult = $result;
        $this->bulkEmails = collect($result['skipped'])->pluck('email')->implode("\n"); // what did not go stays in the box to fix
        $this->team = $this->team->fresh();
    }

    /** Makes a shareable link that brings people in as members. Owners and administrators only; it never carries a higher role. */
    public function createInviteLink(): void
    {
        Gate::authorize('addTeamMember', $this->team);
        abort_unless(TeamRoles::manages(Auth::user(), $this->team), 403);
        abort_if($this->team->personal_team, 403);

        [$link, $token] = TeamInviteLink::issue($this->team, Auth::user(), $this->linkDays, $this->linkMaxUses);
        $this->newLink = route('join.show', $token);

        app(SeatLedger::class)->record($this->team, Auth::user(), 'link_created', 'member link', [
            'link' => $link->id, 'expires_at' => $link->expires_at->toDateString(), 'max_uses' => $link->max_uses,
        ]);
    }

    public function revokeInviteLink(int $linkId): void
    {
        Gate::authorize('addTeamMember', $this->team);
        abort_unless(TeamRoles::manages(Auth::user(), $this->team), 403);

        $link = TeamInviteLink::where('team_id', $this->team->id)->whereKey($linkId)->firstOrFail();
        if ($link->revoked_at === null) {
            $link->forceFill(['revoked_at' => now()])->save();
            app(SeatLedger::class)->record($this->team, Auth::user(), 'link_revoked', 'member link', ['link' => $link->id]);
        }
        $this->newLink = null;
    }
}
