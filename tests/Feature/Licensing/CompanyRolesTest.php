<?php

namespace Tests\Feature\Licensing;

use App\Actions\Jetstream\InviteTeamMember;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** R-13 role vocabulary: owner, admin, manager, member; enforced on the server; wire-compatible with platforms that only know editor. */
class CompanyRolesTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $with = function (string $role) use ($team) {
            $u = User::factory()->create();
            $team->users()->attach($u, ['role' => $role]);

            return $u;
        };

        return [$owner, $team, $with];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['licensing.included_seats' => 10]);
    }

    public function test_the_wire_keeps_the_three_values_older_platforms_understand(): void
    {
        $this->assertSame('owner', TeamRoles::wire('owner'));
        $this->assertSame('admin', TeamRoles::wire('admin'));
        $this->assertSame('editor', TeamRoles::wire('manager'));
        $this->assertSame('editor', TeamRoles::wire('member'));
        $this->assertSame('editor', TeamRoles::wire('editor'));
        $this->assertNull(TeamRoles::wire(null));
        $this->assertSame(['role' => 'editor', 'infodot_role' => 'manager'], TeamRoles::payload('manager'));
        $this->assertSame(['role' => 'editor', 'infodot_role' => 'member'], TeamRoles::payload('editor'));
    }

    public function test_the_old_editor_name_is_a_member(): void
    {
        $this->assertSame('member', TeamRoles::normalise('editor'));
        $this->assertSame('manager', TeamRoles::normalise('manager'));
    }

    public function test_the_migration_renames_editor_rows_and_touches_nothing_else(): void
    {
        [$owner, $team, $with] = $this->company();
        $old = $with('editor');
        $admin = $with('admin');
        $team->teamInvitations()->create(['email' => 'x@example.test', 'role' => 'editor']);

        $migration = require database_path('migrations/2026_10_29_100002_rename_editor_role_to_member.php');
        $migration->up();
        $migration->up(); // safe twice

        $this->assertSame('member', DB::table('team_user')->where('user_id', $old->id)->value('role'));
        $this->assertSame('admin', DB::table('team_user')->where('user_id', $admin->id)->value('role'));
        $this->assertSame('member', DB::table('team_invitations')->where('team_id', $team->id)->value('role'));
        $this->assertTrue($team->fresh()->users->contains($old), 'nobody was removed');
    }

    public function test_who_can_do_what(): void
    {
        [$owner, $team, $with] = $this->company();
        $admin = $with('admin');
        $manager = $with('manager');
        $member = $with('member');

        foreach (['addTeamMember', 'updateTeamMember', 'removeTeamMember', 'viewLicence'] as $ability) {
            $this->assertTrue($owner->can($ability, $team), "owner $ability");
            $this->assertTrue($admin->can($ability, $team), "admin $ability");
        }
        $this->assertTrue($manager->can('addTeamMember', $team));
        foreach (['updateTeamMember', 'removeTeamMember', 'viewLicence', 'changeLicence', 'delete', 'update'] as $ability) {
            $this->assertFalse($manager->can($ability, $team), "manager must not $ability");
        }
        foreach (['addTeamMember', 'updateTeamMember', 'removeTeamMember', 'viewLicence', 'changeLicence', 'delete'] as $ability) {
            $this->assertFalse($member->can($ability, $team), "member must not $ability");
        }
        foreach (['changeLicence', 'delete', 'update'] as $ability) {
            $this->assertFalse($admin->can($ability, $team), "admin must not $ability");
            $this->assertTrue($owner->can($ability, $team));
        }
    }

    public function test_a_manager_can_only_invite_members(): void
    {
        [, $team, $with] = $this->company();
        $manager = $with('manager');

        app(InviteTeamMember::class)->invite($manager, $team, 'a@example.test', 'member');
        $this->assertSame('member', $team->teamInvitations()->where('email', 'a@example.test')->value('role'));

        foreach (['admin', 'manager'] as $role) {
            try {
                app(InviteTeamMember::class)->invite($manager, $team, "$role@example.test", $role);
                $this->fail("a manager must not invite an $role");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('role', $e->errors());
            }
        }
        $this->assertSame(1, $team->teamInvitations()->count());
    }

    public function test_an_admin_can_invite_any_role_and_an_invited_editor_becomes_a_member(): void
    {
        [, $team, $with] = $this->company();
        $admin = $with('admin');

        app(InviteTeamMember::class)->invite($admin, $team, 'a@example.test', 'manager');
        app(InviteTeamMember::class)->invite($admin, $team, 'b@example.test', 'editor');

        $this->assertSame('manager', $team->teamInvitations()->where('email', 'a@example.test')->value('role'));
        $this->assertSame('member', $team->teamInvitations()->where('email', 'b@example.test')->value('role'));
    }

    public function test_a_member_cannot_invite(): void
    {
        [, $team, $with] = $this->company();
        $member = $with('member');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(InviteTeamMember::class)->invite($member, $team, 'a@example.test', 'member');
    }

    public function test_the_handoff_sends_old_and_new_role_side_by_side(): void
    {
        [, $team, $with] = $this->company();
        $manager = $with('manager');

        $payload = TeamRoles::payload($manager->teamRole($team)?->key);
        $this->assertSame(['role' => 'editor', 'infodot_role' => 'manager'], $payload);
    }

    public function test_the_people_page_shows_the_new_names_and_the_picker_hides_editor(): void
    {
        [$owner, $team, $with] = $this->company();
        $with('manager');
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $html = $this->actingAs($owner)->get('/teams/'.$team->id)->assertOk()->getContent();
        $this->assertStringContainsString('Manager', $html);
        $this->assertStringContainsString('Member', $html);
        $this->assertStringContainsString('Administrator', $html);
        $this->assertStringNotContainsString("wire:click=\"\$set('addTeamMemberForm.role', 'editor')\"", $html);
    }
}
