<?php

namespace App\Actions\Jetstream;

use App\Support\Industry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
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
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        $team = $user->ownedTeams()->create([
            'name' => $input['name'],
            'personal_team' => false,
        ]);

        if (filled($input['industry'] ?? null)) {
            $team->forceFill(['industry' => $input['industry']])->save();
        }

        $user->switchTeam($team);

        return $team;
    }
}
