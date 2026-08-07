<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoCurrentTeamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user with no current team (e.g. left/removed from their last team,
     * or created outside the standard registration flow) must not crash
     * every authenticated page. navigation-menu.blade.php's "Manage Team"
     * dropdown previously called Auth::user()->currentTeam->id unguarded
     * behind a Jetstream::hasTeamFeatures() check that only verifies the
     * *feature* is enabled, not that *this user* actually has a team —
     * causing a 500 on every page that renders the shared navigation
     * (dashboard, questions, solutions, sub-services, profile, teams.create
     * itself, and more) the moment current_team_id is null.
     */
    public function test_authenticated_user_with_no_team_does_not_crash_dashboard(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        // /home (the dashboard route) redirects on to /solutions by design
        // for this platform (InfoDot's own community hub, not a dashboard
        // view) — assert it isn't a server error rather than a fixed code.
        $response = $this->actingAs($user)->get('/home');

        $response->assertStatus(302);
        $this->followingRedirects()->actingAs($user)->get('/home')->assertStatus(200);
    }

    public function test_authenticated_user_with_no_team_can_reach_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->get(route('teams.create'));

        $response->assertStatus(200);
    }
}
