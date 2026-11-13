<?php

namespace App\Actions\Jetstream;

use App\Models\BusinessProfileEvent;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Support\Industry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
    /** The business this action just created, for redirectTo(). */
    private ?Team $created = null;

    /**
     * Validate and create a new team for the given user.
     *
     * @param  mixed  $user
     * @return mixed
     */
    public function create($user, array $input)
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'in:'.implode(',', array_keys(Industry::options()))],
            'description' => ['nullable', 'string', 'max:2000'],
            // Who can see the business is the owner's decision, asked here and never assumed (R-48 S-05).
            'visibility' => ['required', Rule::in(array_keys(TeamProfile::VISIBILITY))],
        ], [
            'visibility.required' => 'Choose who can see the business page.',
            'visibility.in' => 'Choose who can see the business page.',
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        // Written as one piece, holding the person's row: an account that is being deleted in another request either
        // finishes first (and nothing is created for it) or waits and then removes this business with the rest.
        $team = DB::transaction(function () use ($user, $input) {
            if (! User::whereKey($user->getKey())->lockForUpdate()->exists()) {
                throw new AuthorizationException;
            }

            $team = $user->ownedTeams()->create([
                'name' => $input['name'],
                'personal_team' => false,
            ]);

            if (filled($input['industry'] ?? null)) {
                $team->forceFill(['industry' => $input['industry']])->save();
            }

            // The profile row is written with the business, so the directory knows where it stands from the first minute.
            $team->profile()->create([
                'visibility' => $input['visibility'],
                'description' => filled($input['description'] ?? null) ? trim((string) $input['description']) : null,
            ]);
            BusinessProfileEvent::record($team, $user, 'listing_changed', 'created as '.$input['visibility']);

            return $team;
        });

        $user->switchTeam($team);

        return $this->created = $team;
    }

    /** Jetstream's create form asks the action where to go next: the new business's listing, with where it stands. */
    public function redirectTo()
    {
        if ($this->created === null) {
            return config('fortify.home');
        }

        $team = $this->created->fresh();

        session()->flash('status', $team->listingMessage());

        return route('business.edit', $team).'#directory';
    }
}
