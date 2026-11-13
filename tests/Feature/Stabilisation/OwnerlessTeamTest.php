<?php

namespace Tests\Feature\Stabilisation;

use App\Actions\Jetstream\CreateTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Tests\TestCase;

/**
 * R-49. Production holds one business whose owner's account no longer exists. Nothing in the schema ties a team to
 * its owner, and the two requests that matter could cross: an account being deleted read its teams once and removed
 * those, while a request that had already signed the same person in went on to create a business for them. Both
 * requests now take the person's row first, so one waits for the other and the second sees what the first did.
 */
class OwnerlessTeamTest extends TestCase
{
    use RefreshDatabase;

    private function ownerless(): int
    {
        return DB::table('teams')->whereNotExists(fn ($q) => $q->from('users')->whereColumn('users.id', 'teams.user_id'))->count();
    }

    public function test_a_request_that_outlived_the_account_cannot_create_a_business_for_it(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $stillSignedIn = User::findOrFail($user->id);

        app(DeletesUsers::class)->delete($user->fresh());

        try {
            app(CreateTeam::class)->create($stillSignedIn, ['name' => 'Late Business', 'visibility' => 'private']);
            $this->fail('A business was created for an account that no longer exists.');
        } catch (AuthorizationException) {
            // Refused.
        }

        $this->assertSame(0, Team::where('name', 'Late Business')->count());
        $this->assertSame(0, $this->ownerless());
    }

    public function test_deleting_an_account_removes_a_business_created_after_its_teams_were_first_read(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $deleting = $user->fresh();
        $deleting->ownedTeams; // what the deleting request already holds in memory

        app(CreateTeam::class)->create(User::findOrFail($user->id), ['name' => 'Made Meanwhile', 'visibility' => 'private']);

        app(DeletesUsers::class)->delete($deleting);

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, Team::where('user_id', $user->id)->count());
        $this->assertSame(0, $this->ownerless());
    }
}
