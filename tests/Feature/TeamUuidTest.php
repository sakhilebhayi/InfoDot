<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A team's uuid is its identity across the Dot ecosystem (other platforms link
 * their own team rows to it): stable, unique, present on every team.
 */
class TeamUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_new_team_gets_a_unique_uuid(): void
    {
        $owner = User::factory()->create();
        $a = Team::forceCreate(['user_id' => $owner->id, 'name' => 'A', 'personal_team' => false]);
        $b = Team::forceCreate(['user_id' => $owner->id, 'name' => 'B', 'personal_team' => false]);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $a->uuid);
        $this->assertNotSame($a->uuid, $b->uuid);
    }

    public function test_renaming_a_team_keeps_its_uuid(): void
    {
        $team = Team::forceCreate(['user_id' => User::factory()->create()->id, 'name' => 'Old', 'personal_team' => false]);
        $uuid = $team->uuid;

        $team->forceFill(['name' => 'New'])->save();

        $this->assertSame($uuid, $team->fresh()->uuid);
    }

    public function test_the_migration_gives_existing_teams_a_uuid(): void
    {
        $owner = User::factory()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Legacy', 'personal_team' => false]);
        DB::table('teams')->where('id', $team->id)->update(['uuid' => null]);

        (require database_path('migrations/2026_10_17_150000_add_uuid_to_teams_table.php'))->up();

        $this->assertNotNull(DB::table('teams')->where('id', $team->id)->value('uuid'));
    }
}
