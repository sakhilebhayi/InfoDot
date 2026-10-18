<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The purpose-built layouts: the Platforms register, the People headcount and roster,
 * the numbered settings index, and the Dot Apps bays with their logos.
 */
class YardPagesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false, 'name' => 'Northwind Finance']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_platforms_is_a_register_with_area_plates_hosts_and_state(): void
    {
        config(['ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true]]);
        config(['ecosystem.platforms.hr' => ['name' => 'Dot.HR', 'url' => 'https://hr.infodot.co.za', 'icon' => 'groups', 'active' => false]]);

        $html = $this->actingAs($this->owner())->get('/platforms')->assertOk()->getContent();

        $this->assertStringContainsString('ix-register', $html);
        $this->assertStringContainsString('WORK', $html);
        $this->assertStringContainsString('files.infodot.co.za', $html);
        $this->assertStringContainsString('Dot.HR is not live yet', $html);
        $this->assertMatchesRegularExpression('#ix-regrow__logo">\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<img#', $html, 'each platform row carries its logo');
    }

    public function test_people_shows_the_headcount_and_each_persons_role(): void
    {
        $owner = $this->owner();
        $team = $owner->currentTeam;
        $editor = User::factory()->create(['email_verified_at' => Carbon::now(), 'current_team_id' => $team->id, 'name' => 'Thabo Mokoena']);
        $team->users()->attach($editor, ['role' => 'editor']);

        $html = $this->actingAs($owner)->get('/people')->assertOk()->assertSee('Thabo Mokoena')->getContent();

        $this->assertStringContainsString('ix-crew__count', $html);
        $this->assertMatchesRegularExpression('#ix-stencil-num">02<#', $html, 'two people in the company');
        $this->assertStringContainsString('ix-crewrow__role is-owner', $html);
    }

    public function test_settings_pages_carry_a_numbered_index_to_their_sections(): void
    {
        $owner = $this->owner();

        $account = $this->actingAs($owner)->get('/user/profile/edit')->assertOk()->getContent();
        $this->assertStringContainsString('ix-settings__index', $account);
        $this->assertStringContainsString('href="#password"', $account);

        $team = $this->actingAs($owner)->get('/teams/'.$owner->currentTeam->id)->assertOk()->getContent();
        $this->assertStringContainsString('href="#members"', $team);
        $this->assertStringContainsString('href="#delete"', $team, 'the owner of a company can see the delete section');
    }

    public function test_dot_apps_lists_bays_with_logos_and_stamps_unavailable_platforms(): void
    {
        config(['ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true]]);
        config(['ecosystem.platforms.hr' => ['name' => 'Dot.HR', 'url' => 'https://hr.infodot.co.za', 'icon' => 'groups', 'active' => false]]);

        $html = $this->actingAs($this->owner())->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString('ix-yardmap', $html);
        $this->assertMatchesRegularExpression('#ix-bay__logo">\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<img#', $html, 'each bay carries its logo');
        $this->assertMatchesRegularExpression('#ix-bay is-soon[^>]*disabled#s', $html);
        $this->assertStringContainsString('Dot.HR is not live yet', $html);
    }
}
