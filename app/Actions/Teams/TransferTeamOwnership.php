<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Licensing\SeatLedger;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Hands a business to one of its existing members (R-49). It exists because an owner who wants to leave InfoDot had
 * no way to do so without deleting the business for everyone in it: account deletion is now refused while the person
 * owns a business with other members, and this is the way out.
 *
 * The two people swap places: the chosen member becomes the owner and the former owner stays in the business with the
 * role the new owner held. Nobody joins or leaves, so the number of seats in use does not change. The change is written
 * to the company's audit trail and both people are told in-app.
 */
class TransferTeamOwnership
{
    public function __construct(private SeatLedger $ledger, private InAppNotices $notices) {}

    /** @return string the role the former owner now holds */
    public function transfer(User $owner, Team $team, User $newOwner): string
    {
        Gate::forUser($owner)->authorize('transferOwnership', $team);

        $role = DB::transaction(function () use ($owner, $team, $newOwner): string {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();

            // Read under the lock: the owner may have changed, or the member been removed, since the form was drawn.
            if ((int) $locked->user_id !== (int) $owner->getKey()) {
                throw ValidationException::withMessages(['new_owner' => 'You no longer own this company.']);
            }

            $membership = DB::table('team_user')->where('team_id', $locked->getKey())->where('user_id', $newOwner->getKey())->first();

            if ($membership === null || $newOwner->is($owner)) {
                throw ValidationException::withMessages(['new_owner' => 'Choose someone who is already in this company.']);
            }

            if ($newOwner->isSuspended()) {
                throw ValidationException::withMessages(['new_owner' => 'This person\'s account is suspended, so they cannot take over the company.']);
            }

            $role = TeamRoles::normalise($membership->role);
            $role = in_array($role, TeamRoles::ASSIGNABLE, true) ? $role : TeamRoles::MEMBER;

            // The owner is the team's user_id and never a row in team_user, so the two swap exactly.
            $locked->users()->detach($newOwner->getKey());
            $locked->forceFill(['user_id' => $newOwner->getKey()])->save();
            $locked->users()->syncWithoutDetaching([$owner->getKey() => ['role' => $role]]);

            $this->ledger->record($locked, $owner, 'ownership_transferred', $newOwner->email, ['from' => $owner->getKey(), 'to' => $newOwner->getKey(), 'former_owner_role' => $role]);

            return $role;
        });

        $team->refresh()->unsetRelations();

        // A notice holds no words and no names; the reference carries which side the reader is on.
        $stamp = now()->format('YmdHis');
        $this->notices->record(UserNotice::BUSINESS_OWNERSHIP, $newOwner, 'business', (int) $team->getKey(), "own:{$team->getKey()}:owner:{$stamp}");
        $this->notices->record(UserNotice::BUSINESS_OWNERSHIP, $owner, 'business', (int) $team->getKey(), "own:{$team->getKey()}:{$role}:{$stamp}");

        return $role;
    }
}
