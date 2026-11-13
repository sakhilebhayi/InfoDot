<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Jetstream\Http\Controllers\TeamInvitationController as JetstreamTeamInvitationController;
use Laravel\Jetstream\Jetstream;

/**
 * Accepting a company invitation. Jetstream's controller accepts any invitation that still exists; here one that has run
 * out is refused with a message that says so and what to do, and the invitation stays so the person who sent it can send
 * it again.
 */
class TeamInvitationController extends JetstreamTeamInvitationController
{
    public function accept(Request $request, $invitationId)
    {
        $model = Jetstream::teamInvitationModel();
        $invitation = $model::whereKey($invitationId)->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect(config('fortify.home'))->dangerBanner(
                __('This invitation to join :company ran out on :date. Ask the person who invited you to send it again.', [
                    'company' => $invitation->team->name,
                    'date' => $invitation->expires_at->timezone(config('app.timezone'))->format('j F Y'),
                ]),
            );
        }

        return parent::accept($request, $invitationId);
    }
}
