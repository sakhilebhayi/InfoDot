<?php

namespace App\Actions\Jetstream;

use App\Mail\MembershipChanged;
use App\Services\Notifications\AccountMail;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given team.
     *
     * @param  mixed  $team
     * @return void
     */
    public function delete($team)
    {
        // R-72 (E-04): the company's other people hear that it is gone. Read before the purge, told after it.
        $members = $team->personal_team ? collect() : $team->allUsers();
        $name = MembershipChanged::plainName($team);

        $team->purge();

        if ($members->isNotEmpty()) {
            app(AccountMail::class)->companyDeleted($members, $name, auth()->user());
        }
    }
}
