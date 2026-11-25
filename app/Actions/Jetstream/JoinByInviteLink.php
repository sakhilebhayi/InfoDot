<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\TeamInviteLink;
use App\Models\User;
use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\SeatLedger;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;

/**
 * Someone who holds a valid link joins the company as a member (R-62 phase 4). The link is re-read inside the seat lock, so a link with
 * one use left cannot be used by two people at once, and a full company refuses the person instead of going over its licences.
 */
class JoinByInviteLink
{
    /** @throws ValidationException when the link cannot be used */
    public function join(User $user, TeamInviteLink $link): Team
    {
        $team = $link->team;

        if ($team === null || $team->personal_team) {
            throw ValidationException::withMessages(['link' => __('This link no longer works.')]);
        }

        if ($team->hasUser($user) || $user->ownsTeam($team)) {
            return $team; // already in: nothing to do, and no use is counted
        }

        AddingTeamMember::dispatch($team, $user);

        app(SeatLedger::class)->guard($team, function () use ($team, $link, $user) {
            $fresh = TeamInviteLink::whereKey($link->getKey())->lockForUpdate()->first();
            if ($fresh === null || $fresh->problem() !== null) {
                throw ValidationException::withMessages(['link' => __('This link no longer works.')]);
            }

            $fresh->increment('uses');
            $team->users()->attach($user, ['role' => 'member']);
            app(LicenceAssignments::class)->recordJoin($team, $user);
        }, null, $user, 'joined', 'via invitation link');

        TeamMemberAdded::dispatch($team, $user);

        return $team;
    }
}
