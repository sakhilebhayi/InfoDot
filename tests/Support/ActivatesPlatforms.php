<?php

namespace Tests\Support;

use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\Entitlements;

/**
 * For tests about what happens once a company holds a platform (R-50): gives a person a company and switches
 * platforms on through the real service, as its owner would. Since R-50 only the free core opens without this.
 */
trait ActivatesPlatforms
{
    /** A company owned by $owner, made their current company, with these platforms switched on. */
    protected function companyWith(User $owner, string ...$platforms): Team
    {
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $owner->unsetRelation('currentTeam');

        $this->activatePlatforms($team, ...$platforms);

        return $team;
    }

    protected function activatePlatforms(Team $team, string ...$platforms): void
    {
        foreach ($platforms as $key) {
            app(Entitlements::class)->activate($team, $key, $team->owner);
        }
    }
}
