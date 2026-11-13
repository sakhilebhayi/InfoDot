<?php

namespace App\Actions\Jetstream;

use App\Services\Licensing\SeatLedger;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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

        // Inviting an address whose earlier invitation ran out is a new invitation, not "already invited".
        $team->teamInvitations()->where('email', $email)->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();

        $this->validate($team, $email, $role);

        InvitingTeamMember::dispatch($team, $email, $role);

        // The seat check and the invitation happen together under a lock on the company, so concurrent invitations cannot exceed capacity.
        $invitation = app(SeatLedger::class)->guard($team, fn () => $team->teamInvitations()->create([
            'email' => $email,
            'role' => $role,
        ]), null, $user, 'invited', $email);

        // Mail goes out inside the request. A refused send must not leave an invitation nobody received holding a seat.
        try {
            Mail::to($email)->send(new TeamInvitation($invitation));
        } catch (TransportExceptionInterface $e) {
            Log::error('team.invitation_mail_failed', [
                'category' => 'mail',
                'team_id' => $team->id,
                'exception' => $e::class,
                'reason' => mb_substr($e->getMessage(), 0, 300),
            ]);

            $invitation->delete();
            app(SeatLedger::class)->record($team, $user, 'invitation_cancelled', $email, ['reason' => 'email_not_sent']);

            throw ValidationException::withMessages([
                'email' => __('The invitation email could not be sent just now, so nobody was invited. Please try again in a few minutes.'),
            ])->errorBag('addTeamMember');
        }
    }

    /**
     * Sends an invitation again and starts its life over. One that had run out holds no seat, so it has to fit like a new one;
     * one that is still live keeps the seat it holds. If the email cannot be sent the invitation is left as it was.
     *
     * @param  mixed  $user
     * @param  mixed  $team
     */
    public function resend($user, $team, \App\Models\TeamInvitation $invitation): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);
        abort_unless((int) $invitation->team_id === (int) $team->id, 404);

        $before = $invitation->expires_at;
        $renew = fn () => $invitation->forceFill(['expires_at' => \App\Models\TeamInvitation::freshExpiry()])->save();

        $invitation->isExpired()
            ? app(SeatLedger::class)->guard($team, $renew, null, $user, 'invited', $invitation->email)
            : $renew();

        try {
            Mail::to($invitation->email)->send(new TeamInvitation($invitation));
        } catch (TransportExceptionInterface $e) {
            Log::error('team.invitation_mail_failed', ['category' => 'mail', 'team_id' => $team->id, 'exception' => $e::class, 'reason' => mb_substr($e->getMessage(), 0, 300)]);
            $invitation->forceFill(['expires_at' => $before])->save();

            throw ValidationException::withMessages([
                'email' => __('The invitation email could not be sent just now, so nothing changed. Please try again in a few minutes.'),
            ])->errorBag('addTeamMember');
        }
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
