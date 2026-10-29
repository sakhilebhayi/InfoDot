<?php

namespace App\Actions\Jetstream;

use App\Services\Licensing\SeatLedger;
use App\Support\TeamRoles;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new team member to the given team.
     *
     * @param  mixed  $user
     * @param  mixed  $team
     * @return void
     */
    public function invite($user, $team, string $email, ?string $role = null)
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $role = TeamRoles::normalise($role);

        if ($role !== null && ! in_array($role, TeamRoles::assignableBy($user, $team), true)) {
            throw ValidationException::withMessages([
                'role' => __('You can invite people as :roles only.', ['roles' => implode(', ', TeamRoles::assignableBy($user, $team))]),
            ])->errorBag('addTeamMember');
        }

        $this->validate($team, $email, $role);

        InvitingTeamMember::dispatch($team, $email, $role);

        // The seat check and the invitation happen together under a lock on the company, so concurrent invitations cannot exceed capacity.
        $invitation = app(SeatLedger::class)->guard($team, fn () => $team->teamInvitations()->create([
            'email' => $email,
            'role' => $role,
        ]), null, $user, 'invited', $email);

        Mail::to($email)->send(new TeamInvitation($invitation));
    }

    /**
     * Validate the invite member operation.
     *
     * @param  mixed  $team
     * @return void
     */
    protected function validate($team, string $email, ?string $role)
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($team), [
            'email.unique' => __('This user has already been invited to the team.'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     *
     * @param  mixed  $team
     * @return array
     */
    protected function rules($team)
    {
        return array_filter([
            'email' => ['required', 'email', Rule::unique('team_invitations')->where(function ($query) use ($team) {
                $query->where('team_id', $team->id);
            })],
            'role' => Jetstream::hasRoles()
                            ? ['required', 'string', new Role]
                            : null,
        ]);
    }

    /**
     * Ensure that the user is not already on the team.
     *
     * @param  mixed  $team
     * @return \Closure
     */
    protected function ensureUserIsNotAlreadyOnTeam($team, string $email)
    {
        return function ($validator) use ($team, $email) {
            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.')
            );
        };
    }
}
