<?php

namespace Tests\Feature\Platforms;

use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\Entitlements;
use App\Services\Platforms\PlatformAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\ActivatesPlatforms;
use Tests\TestCase;

/** Shared people and companies for the R-50 entitlement suites. Passwords come from the factory, never from a literal. */
abstract class PlatformTestCase extends TestCase
{
    use ActivatesPlatforms;
    use RefreshDatabase;

    /** A verified person with the personal workspace every account gets at sign-up, which is their current one. */
    protected function person(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['email_verified_at' => Carbon::now()], $attributes));
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    /** A company owned by a new person, who is acting for it. */
    protected function company(string $name = 'Northwind Freight'): Team
    {
        $owner = $this->person();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => $name]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    /** A new person in the company with this role, acting for it. */
    protected function memberOf(Team $team, string $role = 'member', array $attributes = []): User
    {
        $user = $this->person($attributes);
        $team->users()->attach($user, ['role' => $role]);
        $team->unsetRelation('users'); // Jetstream reads the role from the loaded list
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    protected function administrator(): User
    {
        $user = $this->person();
        $user->forceFill(['is_platform_admin' => true])->save();

        return $user;
    }

    protected function entitlements(): Entitlements
    {
        return app(Entitlements::class);
    }

    protected function access(): PlatformAccess
    {
        return app(PlatformAccess::class);
    }
}
